const WINDOWS_1252_BYTE_BY_CODE_POINT = new Map([
  [0x20ac, 0x80],
  [0x201a, 0x82],
  [0x0192, 0x83],
  [0x201e, 0x84],
  [0x2026, 0x85],
  [0x2020, 0x86],
  [0x2021, 0x87],
  [0x02c6, 0x88],
  [0x2030, 0x89],
  [0x0160, 0x8a],
  [0x2039, 0x8b],
  [0x0152, 0x8c],
  [0x017d, 0x8e],
  [0x2018, 0x91],
  [0x2019, 0x92],
  [0x201c, 0x93],
  [0x201d, 0x94],
  [0x2022, 0x95],
  [0x2013, 0x96],
  [0x2014, 0x97],
  [0x02dc, 0x98],
  [0x2122, 0x99],
  [0x0161, 0x9a],
  [0x203a, 0x9b],
  [0x0153, 0x9c],
  [0x017e, 0x9e],
  [0x0178, 0x9f],
])

const MOJIBAKE_MARKER_REGEX =
  /(?:[ÃƒÃ‚ÃŽÃÃÃ‘ÎÏÐÑ]|\u00e2[\u0080-\u00bf]|ï¿½)/u

const MOJIBAKE_MARKER_GLOBAL_REGEX =
  /(?:[ÃƒÃ‚ÃŽÃÃÃ‘ÎÏÐÑ]|\u00e2[\u0080-\u00bf]|ï¿½)/gu

const GREEK_CHARACTER_REGEX = /[\u0370-\u03ff]/gu

const countMatches = (value, regex) => {
  if (typeof value !== 'string' || !value) return 0

  return value.match(regex)?.length ?? 0
}

const decodeWindows1252Utf8 = (value) => {
  const bytes = []

  for (const char of value) {
    const codePoint = char.codePointAt(0)

    if (WINDOWS_1252_BYTE_BY_CODE_POINT.has(codePoint)) {
      bytes.push(WINDOWS_1252_BYTE_BY_CODE_POINT.get(codePoint))
      continue
    }

    if (codePoint <= 0xff) {
      bytes.push(codePoint)
      continue
    }

    return value
  }

  try {
    return new TextDecoder('utf-8').decode(Uint8Array.from(bytes))
  } catch {
    return value
  }
}

export const normalizePotentialMojibake = (value) => {
  if (typeof value !== 'string' || value === '') return value

  const candidates = [value]
  let current = value

  for (let pass = 0; pass < 8; pass += 1) {
    if (!MOJIBAKE_MARKER_REGEX.test(current)) break

    const decoded = decodeWindows1252Utf8(current)
    if (!decoded || decoded === current) break

    current = decoded
    candidates.push(decoded)
  }

  return candidates.reduce((best, candidate) => {
    const score =
      countMatches(candidate, GREEK_CHARACTER_REGEX) * 5 -
      countMatches(candidate, MOJIBAKE_MARKER_GLOBAL_REGEX) * 4
    const bestScore =
      countMatches(best, GREEK_CHARACTER_REGEX) * 5 -
      countMatches(best, MOJIBAKE_MARKER_GLOBAL_REGEX) * 4

    return score > bestScore ? candidate : best
  }, value)
}

const normalizeLegacyLocalhostUrl = (value) => {
  if (typeof window === 'undefined' || typeof value !== 'string') return value

  return value
    .replace(/^https?:\/\/127\.0\.0\.1:8000\/storage\//, `${window.location.origin}/index.php/storage/`)
    .replace(/^https?:\/\/localhost:8000\/storage\//, `${window.location.origin}/index.php/storage/`)
}

const shouldPreserveValue = (value) => {
  if (!value || typeof value !== 'object') return false

  const constructorName = value.constructor?.name
  return ['Date', 'File', 'Blob', 'FormData', 'URL', 'URLSearchParams'].includes(constructorName)
}

export const normalizeTextTree = (value, seen = new WeakMap()) => {
  if (typeof value === 'string') {
    return normalizeLegacyLocalhostUrl(normalizePotentialMojibake(value))
  }
  if (typeof value === 'function') {
    if (seen.has(value)) {
      return seen.get(value)
    }

    const normalizedFunction = (...args) => normalizeTextTree(value(...args))
    seen.set(value, normalizedFunction)

    Object.entries(value).forEach(([key, entryValue]) => {
      normalizedFunction[key] = normalizeTextTree(entryValue, seen)
    })

    return normalizedFunction
  }
  if (value == null || typeof value !== 'object') return value
  if (shouldPreserveValue(value)) return value

  if (seen.has(value)) {
    return seen.get(value)
  }

  if (Array.isArray(value)) {
    const normalizedArray = []
    seen.set(value, normalizedArray)

    value.forEach((item, index) => {
      normalizedArray[index] = normalizeTextTree(item, seen)
    })

    return normalizedArray
  }

  const normalizedObject = {}
  seen.set(value, normalizedObject)

  Object.entries(value).forEach(([key, entryValue]) => {
    normalizedObject[key] = normalizeTextTree(entryValue, seen)
  })

  return normalizedObject
}
