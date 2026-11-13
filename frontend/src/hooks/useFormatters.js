import { useMemo } from 'react'
import { useI18n } from './useI18n'
import * as formatters from '@/utils/formatters'

export function useFormatters() {
  const { locale } = useI18n()
  return useMemo(() => Object.fromEntries(Object.entries(formatters)
    .map(([key, format]) => [key, (value) => format(value, locale)])), [locale])
}
