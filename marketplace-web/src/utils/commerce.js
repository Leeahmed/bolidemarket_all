export const paymentMethods = {
  MOBILE_MONEY_DEMO: 'Mobile Money — Démonstration',
  CARD_DEMO: 'Carte bancaire — Démonstration',
  CASH_DEMO: 'Espèces — Démonstration',
  BANK_TRANSFER_DEMO: 'Virement — Démonstration',
}
export const reservationStatuses = { pending: 'En attente', confirmed: 'Confirmée', active: 'Active', completed: 'Terminée', cancelled: 'Annulée', rejected: 'Refusée', expired: 'Expirée' }
export const orderStatuses = { pending: 'En attente', confirmed: 'Confirmé', fulfilled: 'Livré', cancelled: 'Annulé' }
export const recordMoney = (record, field = 'total_minor') => record ? ({ amount_minor: record[field], currency: record.currency, minor_unit: record.minor_unit }) : null
export const displayDate = (value, timezone = 'UTC') => value ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeZone: timezone }).format(new Date(value)) : '—'
export function dateInZone(value, timezone = 'UTC') {
  const parts = new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: timezone }).formatToParts(new Date(value))
  return ['year', 'month', 'day'].map(key => parts.find(x => x.type === key).value).join('-')
}
export function addDays(day, count) { const date = new Date(day + 'T12:00:00Z'); date.setUTCDate(date.getUTCDate() + count); return date.toISOString().slice(0, 10) }
export function zonedMidnight(day, timezone) {
  const target = Date.parse(day + 'T00:00:00Z')
  let value = target
  for (let i = 0; i < 3; i++) {
    const parts = new Intl.DateTimeFormat('en-CA', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).formatToParts(new Date(value))
    const p = Object.fromEntries(parts.map(x => [x.type, x.value]))
    const represented = Date.UTC(+p.year, +p.month - 1, +p.day, +p.hour, +p.minute, +p.second)
    value += target - represented
  }
  return value
}
export function rangeAvailable(start, end, availability, now = Date.now()) {
  if (!start || !end || end <= start || !availability || availability.inventory_status !== 'available') return false
  const a = zonedMidnight(start, availability.timezone), b = zonedMidnight(end, availability.timezone)
  if (a < Date.parse(availability.from) || b > Date.parse(availability.to)) return false
  return !availability.intervals.some(x => (!x.expires_at || Date.parse(x.expires_at) > now) && Date.parse(x.starts_at) < b && (!x.ends_at || Date.parse(x.ends_at) > a))
}
export function canCancelReservation(record, now = Date.now()) {
  return record.status === 'pending' && Date.parse(record.expires_at) > now || record.status === 'confirmed' && Date.parse(record.starts_at) > now
}
export const canTransact = (vehicle, type) => vehicle?.inventory_status === 'available' && Boolean(type === 'reserve' ? vehicle.rental_daily_price : vehicle.sale_price)
