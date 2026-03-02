<template>
  <el-dialog
    v-model="visible"
    title="地图选点"
    width="900px"
    destroy-on-close
    :close-on-click-modal="false"
    @open="onOpen"
    @close="onClose"
  >
    <div class="amap-picker">
      <div class="search-row">
        <el-input
          v-model="searchKeyword"
          placeholder="搜索地点（输入后选择或在地图上点击选点）"
          clearable
          class="search-input"
          @keyup.enter="doSearch"
        >
          <template #append>
            <el-button @click="doSearch">搜索</el-button>
          </template>
        </el-input>
      </div>
      <div v-if="loadError" class="map-tip">{{ loadError }}</div>
      <div ref="mapRef" class="map-container" />
      <div v-if="currentResult" class="result-preview">
        <div class="result-label">已选位置：</div>
        <div class="result-text">{{ currentResult.address || [currentResult.longitude, currentResult.latitude].join(', ') }}</div>
        <div class="result-detail">经度 {{ currentResult.longitude }}，纬度 {{ currentResult.latitude }}</div>
      </div>
    </div>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :disabled="!currentResult" @click="onConfirm">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, computed } from 'vue'
import AMapLoader from '@amap/amap-jsapi-loader'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  /** 初始经纬度 [lng, lat] */
  defaultCenter: { type: Array, default: () => null },
})
const emit = defineEmits(['update:modelValue', 'confirm'])

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const mapRef = ref(null)
const searchKeyword = ref('')
let AMap = null
let map = null
let marker = null
let geocoder = null
let placeSearch = null

const currentResult = ref(null)

// 高德 key（请在 .env 中配置 VITE_AMAP_KEY，可选 VITE_AMAP_SECURITY_CODE）
const amapKey = import.meta.env.VITE_AMAP_KEY || ''
const amapSecurity = import.meta.env.VITE_AMAP_SECURITY_CODE || ''

function onOpen() {
  currentResult.value = null
  searchKeyword.value = ''
  if (!amapKey) {
    console.warn('未配置 VITE_AMAP_KEY，地图选点不可用')
    return
  }
  if (amapSecurity) {
    window._AMapSecurityConfig = { securityJsCode: amapSecurity }
  }
  AMapLoader.load({
    key: amapKey,
    version: '2.0',
    plugins: ['AMap.Geocoder', 'AMap.PlaceSearch'],
  })
    .then((AMapInstance) => {
      AMap = AMapInstance
      if (!mapRef.value) return
      const center = props.defaultCenter && props.defaultCenter.length >= 2
        ? props.defaultCenter
        : [116.397428, 39.90923]
      map = new AMap.Map(mapRef.value, {
        zoom: 15,
        center: center,
        viewMode: '2D',
      })
      geocoder = new AMap.Geocoder({ city: '全国', extensions: 'all' })
      placeSearch = new AMap.PlaceSearch({ city: '全国' })
      map.on('click', onMapClick)
    })
    .catch((e) => {
      console.error('高德地图加载失败', e)
    })
}

function doSearch() {
  const keyword = (searchKeyword.value || '').trim()
  if (!keyword || !placeSearch) return
  placeSearch.search(keyword, (status, result) => {
    if (status === 'complete' && result?.poiList?.pois?.length > 0) {
      const poi = result.poiList.pois[0]
      const lng = poi.location?.lng ?? poi.lng
      const lat = poi.location?.lat ?? poi.lat
      if (lng != null && lat != null) {
        map.setCenter([lng, lat])
        map.setZoom(16)
        setMarkerAndGeocode(lng, lat)
        searchKeyword.value = poi.name || keyword
      }
    }
  })
}

function onMapClick(e) {
  if (!e?.lnglat) return
  const lng = e.lnglat.getLng()
  const lat = e.lnglat.getLat()
  setMarkerAndGeocode(lng, lat)
}

function setMarkerAndGeocode(lng, lat) {
  if (!map || !geocoder) return
  if (marker) marker.setMap(null)
  marker = new AMap.Marker({ position: [lng, lat], map })
  map.setCenter([lng, lat])
  geocoder.getAddress([lng, lat], (status, result) => {
    if (status === 'complete' && result?.regeocode) {
      const addr = result.regeocode
      const comp = addr.addressComponent || {}
      currentResult.value = {
        longitude: String(lng),
        latitude: String(lat),
        address: addr.formattedAddress || comp.streetNumber?.street + comp.streetNumber?.number || '',
        province: comp.province || '',
        city: comp.city || comp.province || '',
        district: comp.district || '',
      }
    } else {
      currentResult.value = {
        longitude: String(lng),
        latitude: String(lat),
        address: '',
        province: '',
        city: '',
        district: '',
      }
    }
  })
}

function onConfirm() {
  if (currentResult.value) {
    emit('confirm', { ...currentResult.value })
  }
  visible.value = false
}

function onClose() {
  if (marker) {
    marker.setMap(null)
    marker = null
  }
  if (map) {
    map.destroy()
    map = null
  }
  geocoder = null
  placeSearch = null
  AMap = null
  currentResult.value = null
}
</script>

<style scoped>
.amap-picker { display: flex; flex-direction: column; gap: 12px; }
.search-row { flex-shrink: 0; }
.search-input { width: 100%; }
.map-tip { padding: 12px; background: var(--el-fill-color-light); border-radius: 4px; color: var(--el-color-warning); font-size: 13px; }
.map-container { width: 100%; height: 400px; background: #f0f0f0; }
.result-preview { padding: 10px; background: var(--el-fill-color-light); border-radius: 4px; font-size: 13px; }
.result-label { color: var(--el-text-color-secondary); margin-bottom: 4px; }
.result-text { margin-bottom: 4px; }
.result-detail { color: var(--el-text-color-secondary); }
</style>
