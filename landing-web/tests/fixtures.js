export const vehicle = {
  id: '1', slug: 'toyota-rav4-demo', title: 'Toyota RAV4', brand: { name: 'Toyota' }, model: { name: 'RAV4' },
  year: 2024, transmission: 'automatic', fuel: 'petrol', mileage_km: 12500,
  sale_price: { amount_minor: '18500000', currency: 'XOF', minor_unit: 0 }, rental_daily_price: null,
  primary_image: { url: '/demo.svg', is_placeholder: true }, is_demo: true, distance_km: 2.4,
  location: { district: { id: '3', name: 'Cocody' }, city: { id: '7', name: 'Abidjan' }, country: { code: 'CI', name: 'Côte d’Ivoire' } },
  shop: { slug: 'cocody-demo', name: 'Cocody Auto Selection' },
}
export const shop = { id: '1', name: 'Cocody Auto Selection', slug: 'cocody-demo', is_demo: true,
  location: vehicle.location, published_vehicles_count: 3, merchant: { is_verified: false } }
export const countries = [{ code: 'CI', name: 'Côte d’Ivoire' }, { code: 'FR', name: 'France' }]
export const cities = [{ id: '7', name: 'Abidjan', slug: 'abidjan', country_code: 'CI' }, { id: '8', name: 'Paris', slug: 'paris', country_code: 'FR' }]
export const districts = [{ id: '3', name: 'Cocody', city_id: '7' }]
export const filters = { brands: [{ name: 'Toyota', slug: 'toyota' }], categories: [{ slug: 'suv', label: 'SUV' }] }
