<script setup>
import { labels, statusLabels, date, money } from '../entities'
defineProps({data:Object})
const excluded=['related','activity','images','primary_image','logo_url','cover_url','avatar_url','demo_notice']
function display(value,key,data){if(value===null || value===undefined)return '—';if(typeof value==='boolean')return value?'Oui':'Non';if(key.endsWith('_at') && value)return date(value);if(key.endsWith('_minor') && (data.currency||data.currency_code))return money(value,data.currency||data.currency_code,data.minor_unit||0);return statusLabels[value]||String(value)}
</script>
<template><dl class="data-fields">
  <template v-for="(value,key) in data" :key="key"><template v-if="!excluded.includes(key)">
    <dt>{{ labels[key] || key.replaceAll('_',' ') }}</dt>
    <dd>
      <template v-if="Array.isArray(value)"><span v-if="!value.length">—</span><div v-for="(item,index) in value" :key="index"><DataFields v-if="item && typeof item==='object'" :data="item"/><span v-else>{{ display(item,key,data) }}</span></div></template>
      <span v-else-if="['brand','model','category','country','city','district'].includes(key) && value && typeof value==='object'">{{ value.name||value.label||value.code||'—' }}</span>
      <DataFields v-else-if="value && typeof value==='object'" :data="value"/>
      <span v-else>{{ display(value,key,data) }}</span>
    </dd>
  </template></template>
</dl></template>
