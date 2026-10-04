<script setup>
defineProps({ name: { type: String, default: 'arrow' } })
const paths = {
  arrow: 'M4 12h16m-6-6 6 6-6 6', search: 'm20 20-5-5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0',
  pin: 'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0ZM15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
  car: 'm4 9 2-6h12l2 6M3 9h18v10H3ZM6 19v2m12-2v2M6 13h2m8 0h2',
  shield: 'M12 2 3 6v6c0 6 9 10 9 10s9-4 9-10V6ZM7 12l3 3 7-7',
  users: 'M15 21H2v-4c0-5 13-5 13 0ZM12 6a4 4 0 1 1-8 0 4 4 0 0 1 8 0M17 3c5 0 5 7 0 7m1 4c4 0 4 3 4 7h-4',
  chart: 'M3 21V12h4v9m3 0V7h4v14m3 0V2h4v19M1 22h22',
  heart: 'M12 21 3 12C-3 4 7-1 12 6c5-7 15-2 9 6Z',
  chevron: 'm6 9 6 6 6-6', menu: 'M3 6h18M3 12h18M3 18h18', close: 'm5 5 14 14M5 19 19 5',
  document: 'M5 2h10l4 4v16H5ZM14 2v5h5M8 12h8m-8 4h8', globe: 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18',
  tag: 'M3 12 12 3h9v9l-9 9ZM16 7h.01', check: 'm4 12 5 5L20 6', pause: 'M8 5v14M16 5v14', play: 'm7 4 14 8-14 8Z',
}
</script>
<template><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="paths[name] || paths.arrow" /></svg></template>
