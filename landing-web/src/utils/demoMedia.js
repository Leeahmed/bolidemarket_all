// Editorial illustrations cropped from approved mockups, only for explicitly demo records.
// Actual catalogue photos always take precedence. No catalogue values originate here.
const vehicles = { RAV4: 'rav4', '208': '208', C300: 'c300', Kangoo: 'kangoo', 'Model 3': 'electric' }
const shops = { 'Abidjan Prestige Motors': 'prestige', 'Cocody Auto Selection': 'cocody', 'Lagune Rent Cars': 'lagune', 'Ivoire Premium Auto': 'ivoire' }
export function vehicleMedia(vehicle) {
  const photo = vehicle.primary_image
  if (photo?.url && !photo.is_placeholder) return { src: photo.url, illustration: false }
  if (vehicle.is_demo && vehicles[vehicle.model?.name]) return { src: `/images/vehicle-${vehicles[vehicle.model.name]}.webp`, illustration: true }
  return { src: photo?.url || '', illustration: Boolean(photo?.is_placeholder) }
}
export function shopMedia(shop) {
  return shop.is_demo && shops[shop.name] ? `/images/shop-${shops[shop.name]}.webp` : ''
}
