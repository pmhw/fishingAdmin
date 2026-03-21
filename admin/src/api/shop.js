import request from './request'

/** 钓场下拉（与池塘表单同源） */
export function getVenueOptions() {
  return request.get('/api/admin/venue-options')
}

/** 公共商品规格列表（类似 pond-return-rules?pond_id=） */
export function getShopProductSkuList(productId) {
  return request.get('/api/admin/shop/product-skus', { params: { product_id: productId } })
}

export function createShopProductSku(data) {
  return request.post('/api/admin/shop/product-skus', data)
}

export function updateShopProductSku(skuId, data) {
  return request.put(`/api/admin/shop/product-skus/${skuId}`, data)
}

export function deleteShopProductSku(skuId) {
  return request.delete(`/api/admin/shop/product-skus/${skuId}`)
}

export function getShopProductList(params) {
  return request.get('/api/admin/shop/products', { params })
}

export function getShopProductDetail(id) {
  return request.get(`/api/admin/shop/products/${id}`)
}

export function createShopProduct(data) {
  return request.post('/api/admin/shop/products', data)
}

export function updateShopProduct(id, data) {
  return request.put(`/api/admin/shop/products/${id}`, data)
}

export function deleteShopProduct(id) {
  return request.delete(`/api/admin/shop/products/${id}`)
}

/** 本店商品分类 CRUD */
export function getVenueShopCategories(venueId) {
  return request.get(`/api/admin/shop/venues/${venueId}/categories`)
}

export function createVenueShopCategory(venueId, data) {
  return request.post(`/api/admin/shop/venues/${venueId}/categories`, data)
}

export function updateVenueShopCategory(venueId, categoryId, data) {
  return request.put(`/api/admin/shop/venues/${venueId}/categories/${categoryId}`, data)
}

export function deleteVenueShopCategory(venueId, categoryId) {
  return request.delete(`/api/admin/shop/venues/${venueId}/categories/${categoryId}`)
}

/** 本店已上架商品 */
export function getVenueShopProducts(venueId, params) {
  return request.get(`/api/admin/shop/venues/${venueId}/products`, { params })
}

/** 可选公共商品（未加入本店） */
export function getVenueShopAvailableProducts(venueId, params) {
  return request.get(`/api/admin/shop/venues/${venueId}/available-products`, { params })
}

export function addProductToVenue(venueId, data) {
  return request.post(`/api/admin/shop/venues/${venueId}/products`, data)
}

export function updateVenueShopProduct(venueId, vpId, data) {
  return request.put(`/api/admin/shop/venues/${venueId}/products/${vpId}`, data)
}

export function removeVenueShopProduct(venueId, vpId) {
  return request.delete(`/api/admin/shop/venues/${venueId}/products/${vpId}`)
}

export function batchUpdateVenueShopSkus(venueId, items) {
  return request.put(`/api/admin/shop/venues/${venueId}/skus/batch`, { items })
}

export function syncVenueShopSkus(venueId, vpId) {
  return request.post(`/api/admin/shop/venues/${venueId}/products/${vpId}/sync`)
}

export function uploadShopImage(file) {
  const formData = new FormData()
  formData.append('file', file)
  return request.post('/api/admin/upload/image', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}
