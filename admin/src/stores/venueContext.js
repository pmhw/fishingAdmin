import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { getVenueOptions } from '@/api/pond'

const STORAGE_KEY = 'admin_global_venue'

function readStoredVenue() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return { id: null, name: '' }
    const o = JSON.parse(raw)
    const id = o?.id != null ? Number(o.id) : null
    if (id && !Number.isNaN(id)) {
      return { id, name: o?.name != null ? String(o.name) : '' }
    }
  } catch {
    /* ignore */
  }
  return { id: null, name: '' }
}

const _initial = readStoredVenue()

export const useVenueContextStore = defineStore('venueContext', () => {
  const venueId = ref(_initial.id)
  const venueName = ref(_initial.name)
  const options = ref([])
  const optionsLoaded = ref(false)

  const hasVenue = computed(() => venueId.value != null && venueId.value !== '')

  /** 登录后或需与 localStorage 强制对齐时调用 */
  function hydrateFromStorage() {
    const { id, name } = readStoredVenue()
    venueId.value = id
    venueName.value = name
  }

  function persist() {
    try {
      if (venueId.value != null && venueId.value !== '') {
        localStorage.setItem(
          STORAGE_KEY,
          JSON.stringify({ id: venueId.value, name: venueName.value || '' })
        )
      } else {
        localStorage.removeItem(STORAGE_KEY)
      }
    } catch {
      /* ignore */
    }
  }

  function syncNameFromOptions() {
    if (!venueId.value || !options.value.length) return
    const v = options.value.find((x) => Number(x.id) === Number(venueId.value))
    if (v?.name) {
      venueName.value = v.name
      persist()
    }
  }

  /**
   * @param {number|string|null|undefined} id
   * @param {string} [name]
   */
  function setVenue(id, name) {
    if (id == null || id === '') {
      venueId.value = null
      venueName.value = ''
      persist()
      return
    }
    const n = Number(id)
    venueId.value = Number.isNaN(n) ? null : n
    if (name) {
      venueName.value = String(name)
    } else {
      syncNameFromOptions()
      if (!venueName.value) {
        const v = options.value.find((x) => Number(x.id) === Number(venueId.value))
        venueName.value = v?.name ? String(v.name) : ''
      }
    }
    persist()
  }

  function clearVenue() {
    venueId.value = null
    venueName.value = ''
    persist()
  }

  async function loadOptions() {
    try {
      const res = await getVenueOptions()
      const data = res?.data ?? res
      const list = Array.isArray(data) ? data : data?.list ?? []
      options.value = list
      syncNameFromOptions()
    } catch {
      options.value = []
    } finally {
      optionsLoaded.value = true
    }
  }

  return {
    venueId,
    venueName,
    options,
    optionsLoaded,
    hasVenue,
    hydrateFromStorage,
    setVenue,
    clearVenue,
    loadOptions,
    persist,
  }
})
