import { majorToMinor, minorToMajor } from './filters'
export const labels = { available:'Disponible', rented:'Loué', sold:'Vendu', other:'Autre / maintenance', draft:'Brouillon', published:'Publié', archived:'Archivé', pending:'En attente', confirmed:'Confirmée', active:'En cours', completed:'Terminée', cancelled:'Annulée', rejected:'Refusée', expired:'Expirée', fulfilled:'Livrée', paid:'Payé (DEMO)' }
export const date = value => value ? new Intl.DateTimeFormat('fr-FR', { dateStyle:'medium' }).format(new Date(value)) : '—'
export const moneyOf = r => ({ amount_minor:r.total_minor, currency:r.currency, minor_unit:r.minor_unit })
export const offerLabel = v => v.offer_types?.includes('sale') && v.offer_types?.includes('rent') ? 'Vente + location' : v.offer_types?.includes('sale') ? 'À vendre' : 'À louer'
export function vehiclePayload(form, shop, editing) {
  const result = { ...form, vehicle_model_id:form.vehicle_model_id, is_for_sale:form.offer !== 'rental', is_for_rent:form.offer !== 'sale' }
  result.sale_price_minor = result.is_for_sale ? majorToMinor(form.sale, shop.currency.minor_unit) : null
  result.rent_daily_minor = result.is_for_rent ? majorToMinor(form.rent, shop.currency.minor_unit) : null
  if (result.is_for_sale && (!result.sale_price_minor || BigInt(result.sale_price_minor) <= 0n)) throw new Error('Le prix de vente est obligatoire et doit être positif.')
  if (result.is_for_rent && (!result.rent_daily_minor || BigInt(result.rent_daily_minor) <= 0n)) throw new Error('Le tarif de location est obligatoire et doit être positif.')
  for (const k of ['offer','sale','rent','brand_id']) delete result[k]
  for (const k of ['district_id','latitude','longitude','mileage_km','horsepower','doors','seats']) if (result[k] === '') result[k] = null
  if (!editing) result.shop_id = shop.id
  return result
}
export const major = money => money ? minorToMajor(money.amount_minor, money.minor_unit) : ''
