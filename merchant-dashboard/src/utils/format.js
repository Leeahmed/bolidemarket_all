const number = new Intl.NumberFormat('fr-FR')
export const formatNumber = (value) => number.format(value)
export function formatMoney(money) {
  if (!money || !/^\d+$/.test(String(money.amount_minor))) return 'Prix non renseigné'
  const unit = Number(money.minor_unit)
  const amount = Number(money.amount_minor) / 10 ** unit
  const currency = money.currency
  return new Intl.NumberFormat(['USD', 'CAD'].includes(currency) ? 'en-US' : 'fr-FR', { style: 'currency', currency, currencyDisplay: 'symbol', minimumFractionDigits: Number(money.amount_minor) % (10 ** unit) ? unit : 0, maximumFractionDigits: unit })
    .format(amount).replace(/F\s*CFA|XOF/g, 'FCFA')
}
export const formatDistance = (value) => value === null || value === undefined ? '' : `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(value)} km`
export const locationLabel = (location) => [location?.district?.name, location?.city?.name].filter(Boolean).join(', ') || location?.country?.name || 'Localisation à préciser'
export const fuelLabel = { petrol: 'Essence', diesel: 'Diesel', hybrid: 'Hybride', electric: 'Électrique' }
export const transmissionLabel = { automatic: 'Automatique', manual: 'Manuelle' }
