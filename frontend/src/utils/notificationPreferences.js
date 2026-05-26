export const defaultNotificationPreferences = {
  messages: { in_app: true, email: true },
  orders: { in_app: true, email: true },
  follows: { in_app: true, email: true },
  support: { in_app: true, email: true },
  security: { in_app: true, email: true, locked: true },
}

export const normalizeNotificationPreferences = (input) => {
  const source = input && typeof input === 'object' ? input : {}

  return Object.entries(defaultNotificationPreferences).reduce((accumulator, [key, defaults]) => {
    const section = source[key] && typeof source[key] === 'object' ? source[key] : {}

    accumulator[key] = {
      in_app:
        defaults.locked
          ? true
          : Boolean(section.in_app ?? section.inApp ?? defaults.in_app),
      email:
        defaults.locked
          ? true
          : Boolean(section.email ?? defaults.email),
      ...(defaults.locked ? { locked: true } : {}),
    }

    return accumulator
  }, {})
}
