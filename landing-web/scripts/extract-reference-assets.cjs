// Lossless source crops, then WebP delivery encoding. Never modify the official references.
// Run with sharp available (the Codex bundled NODE_PATH on this workstation).
const sharp = require('sharp')
const fs = require('node:fs/promises')
const path = require('node:path')
const root = path.resolve(__dirname, '..')
const refs = path.resolve(root, '../docs/references')
const out = path.join(root, 'public/images')
const crops = {
  'logo-horizontal': ['logo-lockups', 600, 187, 847, 172],
  'hero-auto-poster': ['landing-hero', 770, 115, 816, 600],
  'pro-dashboard': ['bolidemarket-pro', 575, 86, 1011, 851],
  'mobile-preview': ['mobile-app', 698, 20, 855, 946],
  'store-badges': ['mobile-app', 69, 665, 581, 92],
  'app-store': ['mobile-app', 71, 668, 272, 87],
  'google-play': ['mobile-app', 366, 668, 281, 87],
  'vehicle-electric': ['mobile-app', 1354, 252, 129, 96],
  'vehicle-rav4': ['vehicle-cards', 58, 365, 351, 203],
  'vehicle-208': ['vehicle-cards', 432, 365, 350, 203],
  'vehicle-c300': ['vehicle-cards', 803, 365, 351, 203],
  'vehicle-kangoo': ['vehicle-cards', 1174, 365, 351, 203],
  'shop-prestige': ['professionals', 33, 283, 372, 326],
  'shop-cocody': ['professionals', 418, 283, 370, 326],
  'shop-lagune': ['professionals', 800, 283, 368, 326],
  'shop-ivoire': ['professionals', 1183, 283, 371, 326],
  'buy-auto': ['landing-full', 195, 669, 165, 149],
  'rent-auto': ['landing-full', 540, 669, 176, 149],
  'final-auto': ['landing-full', 410, 1881, 321, 87],
  'international': ['landing-full', 28, 1778, 675, 42],
}
async function main() {
  await fs.mkdir(out, { recursive: true })
  for (const [name, [source, left, top, width, height]] of Object.entries(crops)) {
    await sharp(path.join(refs, source + '.png')).extract({ left, top, width, height }).webp({ quality: 88 }).toFile(path.join(out, name + '.webp'))
  }
  await sharp(path.join(refs, 'logo-final.png')).resize(64, 64).png().toFile(path.join(out, 'favicon.png'))
  console.log('Extracted', Object.keys(crops).length, 'reference crops; originals preserved.')
}
main().catch((error) => { console.error(error); process.exitCode = 1 })
