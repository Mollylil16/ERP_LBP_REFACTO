/**
 * Parsing de dates API pour Ant Design DatePicker (évite décalage UTC sur YYYY-MM-DD).
 */
import dayjs, { type Dayjs } from 'dayjs'
import customParseFormat from 'dayjs/plugin/customParseFormat'

dayjs.extend(customParseFormat)

/**
 * Convertit une valeur API en Dayjs pour `DatePicker` (value prop).
 * Les dates « date seule » YYYY-MM-DD sont interprétées en calendrier local.
 */
export function dayjsFromApiForPicker(
  value: string | Date | null | undefined,
): Dayjs | null {
  if (value == null || value === '') return null
  if (value instanceof Date) {
    return dayjs(value).isValid() ? dayjs(value) : null
  }
  const s = String(value).trim()
  if (!s) return null
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
    const d = dayjs(s, 'YYYY-MM-DD', true)
    return d.isValid() ? d : null
  }
  const d = dayjs(s)
  return d.isValid() ? d : null
}
