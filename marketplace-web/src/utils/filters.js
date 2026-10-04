export const filterKeys = ['market', 'q', 'listing_type', 'brand', 'model', 'category', 'condition', 'fuel_type', 'transmission', 'min_price', 'max_price', 'currency', 'year_min', 'year_max', 'country_code', 'city_id', 'district_id', 'location_mode', 'latitude', 'longitude', 'radius', 'status', 'is_certified', 'is_featured', 'sort', 'page']
export function cleanQuery(query) {
  const result = {}
  for (const key of filterKeys) {
    const value = Array.isArray(query[key]) ? query[key][0] : query[key]
    if (value !== undefined && value !== null && String(value).trim() !== '') result[key] = String(value).trim()
  }
  if (!result.country_code && query.country_id) result.country_code = String(query.country_id)
  return result
}
export function activeCount(query) {
  return Object.keys(cleanQuery(query)).filter(key => !['market', 'page', 'sort', 'location_mode', 'latitude', 'longitude'].includes(key)).length
}
export function locationText(query, refs) {
  if ([query.latitude, query.longitude].every(value => value != null && value !== '')) return 'Votre position GPS'
  return [refs.districts.find(x => x.id === query.district_id)?.name, refs.cities.find(x => x.id === query.city_id)?.name, !query.city_id && refs.countries.find(x => x.code === query.country_code)?.name].filter(Boolean).join(', ') || 'Toutes les localisations'
}
export function majorToMinor(value, unit = 0) {
  if (value === '' || value == null) return ''
  const text = String(value).trim().replace(',', '.')
  if (!/^\d+(\.\d+)?$/.test(text)) throw new Error('Saisissez un prix positif valide.')
  const [whole, fraction = ''] = text.split('.')
  if (fraction.length > unit) throw new Error(`Cette devise accepte ${unit} décimale(s).`)
  const minor = BigInt(whole) * 10n ** BigInt(unit) + BigInt(fraction.padEnd(unit, '0') || '0')
  if (minor > 99999999999999n) throw new Error('Le prix dépasse la limite autorisée.')
  return String(minor)
}
export function minorToMajor(value, unit = 0) {
  if (value === undefined || value === '') return ''
  if (!/^\d+$/.test(String(value))) return String(value)
  const text = String(value).padStart(unit + 1, '0')
  return unit ? `${text.slice(0, -unit)}.${text.slice(-unit)}` : text
}
