<?php
declare(strict_types=1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\Product;
use app\model\ProductSku;
use app\model\VenueProduct;
use app\model\VenueShopCategory;
use app\model\VenueProductSku;
use think\facade\Db;
use think\response\Json;

/**
 * 后台 - 钓场店铺：选品、库存与售价
 */
class VenueShopController extends BaseController
{
    use VenueScopeTrait;

    /**
     * 本店已选商品列表 GET /api/admin/shop/venues/:venue_id/products
     */
    public function list(int $venue_id): Json
    {
        $venueId = $venue_id;
        if (!$this->canAccessVenue($venueId)) {
            return json(['code' => 403, 'msg' => '无权管理该钓场店铺', 'data' => null]);
        }

        $page = max((int) $this->request->get('page', 1), 1);
        $limit = min(max((int) $this->request->get('limit', 10), 1), 100);
        $shopCat = $this->request->get('shop_category_id');

        $query = VenueProduct::with(['product', 'shopCategory'])
            ->where('venue_id', $venueId)
            ->order('sort_order', 'asc')
            ->order('id', 'desc');

        if ($shopCat !== null && $shopCat !== '') {
            $cid = (int) $shopCat;
            if ($cid < 1) {
                $query->whereRaw('(shop_category_id IS NULL OR shop_category_id = 0)');
            } else {
                $query->where('shop_category_id', $cid);
            }
        }

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $items = [];
        foreach ($paginator->items() as $vp) {
            $items[] = $this->venueProductRowToApi($vp, true);
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => ['list' => $items, 'total' => $paginator->total()],
        ]);
    }

    /**
     * 可选公共商品（尚未加入本店）GET /api/admin/shop/venues/:venue_id/available-products
     */
    public function availableProducts(int $venue_id): Json
    {
        $venueId = $venue_id;
        if (!$this->canAccessVenue($venueId)) {
            return json(['code' => 403, 'msg' => '无权管理该钓场店铺', 'data' => null]);
        }

        $page = max((int) $this->request->get('page', 1), 1);
        $limit = min(max((int) $this->request->get('limit', 10), 1), 100);
        $keyword = trim((string) $this->request->get('keyword', ''));

        $picked = VenueProduct::where('venue_id', $venueId)->column('product_id');
        $picked = array_map('intval', is_array($picked) ? $picked : []);

        $query = Product::where('status', 1)->order('sort_order', 'asc')->order('id', 'desc');
        if (!empty($picked)) {
            $query->whereNotIn('id', $picked);
        }
        if ($keyword !== '') {
            $query->whereLike('name', '%' . addcslashes($keyword, '%_\\') . '%');
        }

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $items = [];
        foreach ($paginator->items() as $row) {
            $arr = $row->toArray();
            $arr['images'] = $this->decodeImages($arr['images'] ?? null);
            $arr['sku_count'] = (int) ProductSku::where('product_id', (int) $row->id)->where('status', 1)->count();
            $items[] = $arr;
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => ['list' => $items, 'total' => $paginator->total()],
        ]);
    }

    /**
     * 本店添加商品 POST /api/admin/shop/venues/:venue_id/products
     * body: { product_id }
     */
    public function addProduct(int $venue_id): Json
    {
        $venueId = $venue_id;
        if (!$this->canAccessVenue($venueId)) {
            return json(['code' => 403, 'msg' => '无权管理该钓场店铺', 'data' => null]);
        }

        $productId = (int) $this->request->post('product_id', 0);
        if ($productId < 1) {
            return json(['code' => 400, 'msg' => '请传入 product_id', 'data' => null]);
        }

        $product = Product::find($productId);
        if (!$product || (int) ($product->status ?? 0) !== 1) {
            return json(['code' => 400, 'msg' => '商品不存在或已停用', 'data' => null]);
        }

        $exists = VenueProduct::where('venue_id', $venueId)->where('product_id', $productId)->find();
        if ($exists) {
            return json(['code' => 400, 'msg' => '该商品已在店铺中', 'data' => null]);
        }

        $skus = ProductSku::where('product_id', $productId)->where('status', 1)->order('sort_order', 'asc')->order('id', 'asc')->select();
        if ($skus->isEmpty()) {
            return json(['code' => 400, 'msg' => '该商品暂无启用中的规格，请先在商品库中配置规格', 'data' => null]);
        }

        $shopCategoryId = $this->request->post('shop_category_id');
        $shopCategoryId = $shopCategoryId === null || $shopCategoryId === '' ? null : (int) $shopCategoryId;
        if ($shopCategoryId !== null && $shopCategoryId > 0) {
            $cat = VenueShopCategory::where('id', $shopCategoryId)->where('venue_id', $venueId)->where('status', 1)->find();
            if (!$cat) {
                return json(['code' => 400, 'msg' => '本店分类不存在或已停用', 'data' => null]);
            }
        } else {
            $shopCategoryId = null;
        }

        $vpId = 0;
        Db::startTrans();
        try {
            $vp = VenueProduct::create([
                'venue_id'          => $venueId,
                'product_id'        => $productId,
                'shop_category_id'  => $shopCategoryId,
                'status'            => 1,
                'sort_order'        => (int) $this->request->post('sort_order', 0),
            ]);
            $vpId = (int) $vp->id;
            foreach ($skus as $sku) {
                $def = $sku->default_price;
                $price = $def !== null && $def !== '' ? round((float) $def, 2) : 0.0;
                VenueProductSku::create([
                    'venue_product_id' => $vpId,
                    'product_sku_id'   => (int) $sku->id,
                    'price'            => $price,
                    'stock'            => 0,
                    'status'           => 1,
                ]);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();

            return json(['code' => 500, 'msg' => '添加失败：' . $e->getMessage(), 'data' => null]);
        }

        $vp = VenueProduct::with(['product', 'shopCategory'])->find($vpId);

        return json(['code' => 0, 'msg' => '已加入店铺', 'data' => $this->venueProductRowToApi($vp, true)]);
    }

    /**
     * 更新本店商品（排序/上下架/本店分类）PUT /api/admin/shop/venues/:venue_id/products/:vp_id
     */
    public function updateVenueProduct(int $venue_id, int $vp_id): Json
    {
        $venueId = $venue_id;
        if (!$this->canAccessVenue($venueId)) {
            return json(['code' => 403, 'msg' => '无权管理该钓场店铺', 'data' => null]);
        }

        $vp = VenueProduct::where('id', $vp_id)->where('venue_id', $venueId)->find();
        if (!$vp) {
            return json(['code' => 404, 'msg' => '记录不存在', 'data' => null]);
        }

        $data = [];
        $st = $this->request->param('status');
        if ($st !== null && $st !== '') {
            $data['status'] = (int) $st;
        }
        $so = $this->request->param('sort_order');
        if ($so !== null && $so !== '') {
            $data['sort_order'] = (int) $so;
        }
        if ($this->request->param('shop_category_id') !== null) {
            $sc = $this->request->param('shop_category_id');
            if ($sc === '' || $sc === null) {
                $data['shop_category_id'] = null;
            } else {
                $cid = (int) $sc;
                if ($cid < 1) {
                    $data['shop_category_id'] = null;
                } else {
                    $cat = VenueShopCategory::where('id', $cid)->where('venue_id', $venueId)->where('status', 1)->find();
                    if (!$cat) {
                        return json(['code' => 400, 'msg' => '本店分类不存在或已停用', 'data' => null]);
                    }
                    $data['shop_category_id'] = $cid;
                }
            }
        }
        if (!empty($data)) {
            $vp->save($data);
        }
        $vp = VenueProduct::with(['product', 'shopCategory'])->find($vp_id);

        return json(['code' => 0, 'msg' => '保存成功', 'data' => $this->venueProductRowToApi($vp, true)]);
    }

    /**
     * 移除本店商品 DELETE /api/admin/shop/venues/:venue_id/products/:vp_id
     */
    public function removeProduct(int $venue_id, int $vp_id): Json
    {
        $venueId = $venue_id;
        if (!$this->canAccessVenue($venueId)) {
            return json(['code' => 403, 'msg' => '无权管理该钓场店铺', 'data' => null]);
        }

        $vp = VenueProduct::where('id', $vp_id)->where('venue_id', $venueId)->find();
        if (!$vp) {
            return json(['code' => 404, 'msg' => '记录不存在', 'data' => null]);
        }
        $vp->delete();

        return json(['code' => 0, 'msg' => '已移除', 'data' => null]);
    }

    /**
     * 批量更新本店 SKU 售价/库存/可售状态
     * PUT /api/admin/shop/venues/:venue_id/skus/batch
     * body: { items: [ { id: venue_product_sku_id, price?, stock?, status? }, ... ] }
     */
    public function batchUpdateSkus(int $venue_id): Json
    {
        $venueId = $venue_id;
        if (!$this->canAccessVenue($venueId)) {
            return json(['code' => 403, 'msg' => '无权管理该钓场店铺', 'data' => null]);
        }

        $items = $this->request->param('items');
        if (!is_array($items)) {
            $raw = json_decode((string) $this->request->getContent(), true);
            $items = is_array($raw['items'] ?? null) ? $raw['items'] : [];
        }
        if ($items === []) {
            return json(['code' => 400, 'msg' => '请传入 items 数组', 'data' => null]);
        }

        Db::startTrans();
        try {
            foreach ($items as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $vpsId = (int) ($it['id'] ?? 0);
                if ($vpsId < 1) {
                    continue;
                }
                /** @var VenueProductSku|null $vps */
                $vps = VenueProductSku::find($vpsId);
                if (!$vps) {
                    Db::rollback();

                    return json(['code' => 400, 'msg' => '无效的店内规格 id：' . $vpsId, 'data' => null]);
                }
                $vp = VenueProduct::find((int) $vps->venue_product_id);
                if (!$vp || (int) $vp->venue_id !== $venueId) {
                    Db::rollback();

                    return json(['code' => 400, 'msg' => '无权修改该规格：' . $vpsId, 'data' => null]);
                }
                $data = [];
                if (array_key_exists('price', $it)) {
                    $data['price'] = round((float) $it['price'], 2);
                }
                if (array_key_exists('stock', $it)) {
                    $data['stock'] = max(0, (int) $it['stock']);
                }
                if (array_key_exists('status', $it) && $it['status'] !== null && $it['status'] !== '') {
                    $data['status'] = (int) $it['status'];
                }
                if (!empty($data)) {
                    $vps->save($data);
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();

            return json(['code' => 500, 'msg' => '保存失败：' . $e->getMessage(), 'data' => null]);
        }

        return json(['code' => 0, 'msg' => '保存成功', 'data' => null]);
    }

    /**
     * 将公共库新增规格同步到本店（补行，不影响已有行）
     * POST /api/admin/shop/venues/:venue_id/products/:vp_id/sync-skus
     */
    public function syncSkus(int $venue_id, int $vp_id): Json
    {
        $venueId = $venue_id;
        if (!$this->canAccessVenue($venueId)) {
            return json(['code' => 403, 'msg' => '无权管理该钓场店铺', 'data' => null]);
        }

        $vp = VenueProduct::where('id', $vp_id)->where('venue_id', $venueId)->find();
        if (!$vp) {
            return json(['code' => 404, 'msg' => '记录不存在', 'data' => null]);
        }

        $productId = (int) $vp->product_id;
        $existing = VenueProductSku::where('venue_product_id', $vp_id)->column('product_sku_id');
        $existing = array_map('intval', is_array($existing) ? $existing : []);

        $skus = ProductSku::where('product_id', $productId)->where('status', 1)->select();
        $added = 0;
        Db::startTrans();
        try {
            foreach ($skus as $sku) {
                $sid = (int) $sku->id;
                if (in_array($sid, $existing, true)) {
                    continue;
                }
                $def = $sku->default_price;
                $price = $def !== null && $def !== '' ? round((float) $def, 2) : 0.0;
                VenueProductSku::create([
                    'venue_product_id' => $vp_id,
                    'product_sku_id'   => $sid,
                    'price'            => $price,
                    'stock'            => 0,
                    'status'           => 1,
                ]);
                ++$added;
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();

            return json(['code' => 500, 'msg' => '同步失败：' . $e->getMessage(), 'data' => null]);
        }

        $vp = VenueProduct::with(['product', 'shopCategory'])->find($vp_id);

        return json([
            'code' => 0,
            'msg'  => '同步完成，新增 ' . $added . ' 条规格',
            'data' => $this->venueProductRowToApi($vp, true),
        ]);
    }

    private function venueProductRowToApi(VenueProduct $vp, bool $withSkus): array
    {
        $arr = $vp->toArray();
        $p = $vp->product;
        $arr['product'] = null;
        $c = $vp->shopCategory;
        $arr['shop_category'] = $c ? $c->toArray() : null;
        if ($p) {
            $pa = $p->toArray();
            $pa['images'] = $this->decodeImages($pa['images'] ?? null);
            $arr['product'] = $pa;
        }
        $arr['skus'] = [];
        if ($withSkus) {
            $lines = VenueProductSku::with(['productSku'])
                ->where('venue_product_id', (int) $vp->id)
                ->order('id', 'asc')
                ->select();
            foreach ($lines as $line) {
                $arr['skus'][] = $this->venueProductSkuToApi($line);
            }
        }

        return $arr;
    }

    private function venueProductSkuToApi(VenueProductSku $line): array
    {
        $a = $line->toArray();
        $ps = $line->productSku;
        $a['product_sku'] = null;
        if ($ps) {
            $sa = $ps->toArray();
            $sa['spec_json'] = $this->decodeSpecJson($sa['spec_json'] ?? null);
            $a['product_sku'] = $sa;
        }

        return $a;
    }

    private function decodeImages(mixed $v): array
    {
        if (is_array($v)) {
            return $v;
        }
        if (!is_string($v) || $v === '') {
            return [];
        }
        $j = json_decode($v, true);

        return is_array($j) ? $j : [];
    }

    private function decodeSpecJson(mixed $v): ?array
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_array($v)) {
            return $v;
        }
        if (is_string($v)) {
            $j = json_decode($v, true);

            return is_array($j) ? $j : null;
        }

        return null;
    }
}
