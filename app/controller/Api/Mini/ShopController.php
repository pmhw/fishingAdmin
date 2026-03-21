<?php
declare(strict_types=1);

namespace app\controller\Api\Mini;

use app\BaseController;
use app\model\FishingVenue;
use app\model\Product;
use app\model\VenueProduct;
use app\model\VenueProductSku;
use app\model\VenueShopCategory;
use think\response\Json;

/**
 * 小程序端 - 钓场店铺商品（分类 / 列表 / 详情含规格）
 *
 * 无需登录；仅展示钓场 status=1 且本店在售 venue_product.status=1 的数据
 */
class ShopController extends BaseController
{
    /**
     * 本店商品分类 GET /api/mini/venues/:venue_id/shop/categories
     */
    public function categories(int $venue_id): Json
    {
        $venueId = $venue_id;
        if (!$this->isVenueVisible($venueId)) {
            return json(['code' => 404, 'msg' => '钓场不存在或已下架', 'data' => ['list' => []]]);
        }

        $rows = VenueShopCategory::where('venue_id', $venueId)
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'id'         => (int) $r->id,
                'name'       => (string) ($r->name ?? ''),
                'sort_order' => (int) ($r->sort_order ?? 0),
            ];
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list]]);
    }

    /**
     * 本店商品列表 GET /api/mini/venues/:venue_id/shop/products
     * query: page, limit, shop_category_id（可选，不传=全部；0=仅未分组）
     */
    public function productList(int $venue_id): Json
    {
        $venueId = $venue_id;
        if (!$this->isVenueVisible($venueId)) {
            return json(['code' => 404, 'msg' => '钓场不存在或已下架', 'data' => ['list' => [], 'total' => 0]]);
        }

        $page  = max((int) $this->request->param('page', 1), 1);
        $limit = min(max((int) $this->request->param('limit', 10), 1), 50);
        $shopCat = $this->request->param('shop_category_id');

        $activeIds = Product::where('status', 1)->column('id');
        $activeIds = array_values(array_unique(array_map('intval', is_array($activeIds) ? $activeIds : [])));

        $query = VenueProduct::with(['product', 'shopCategory'])
            ->where('venue_id', $venueId)
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'desc');

        if ($activeIds === []) {
            $query->whereRaw('1=0');
        } else {
            $query->whereIn('product_id', $activeIds);
        }

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
            $items[] = $this->venueProductToListItem($vp);
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => ['list' => $items, 'total' => (int) $paginator->total()],
        ]);
    }

    /**
     * 商品详情（含可售规格）GET /api/mini/venues/:venue_id/shop/products/:vp_id
     * vp_id = venue_product 表主键
     */
    public function productDetail(int $venue_id, int $vp_id): Json
    {
        $venueId = $venue_id;
        if (!$this->isVenueVisible($venueId)) {
            return json(['code' => 404, 'msg' => '钓场不存在或已下架', 'data' => null]);
        }

        /** @var VenueProduct|null $vp */
        $vp = VenueProduct::with(['product', 'shopCategory'])
            ->where('id', $vp_id)
            ->where('venue_id', $venueId)
            ->find();

        if (!$vp || (int) ($vp->status ?? 0) !== 1) {
            return json(['code' => 404, 'msg' => '商品不存在或已下架', 'data' => null]);
        }

        $product = $vp->product;
        if (!$product || (int) ($product->status ?? 0) !== 1) {
            return json(['code' => 404, 'msg' => '商品不存在或已下架', 'data' => null]);
        }

        $skus = VenueProductSku::with(['productSku'])
            ->where('venue_product_id', (int) $vp->id)
            ->where('status', 1)
            ->order('id', 'asc')
            ->select();

        $skuList = [];
        $minPrice = null;
        foreach ($skus as $line) {
            $ps = $line->productSku;
            if (!$ps || (int) ($ps->status ?? 0) !== 1) {
                continue;
            }
            $price = round((float) ($line->price ?? 0), 2);
            $stock = (int) ($line->stock ?? 0);
            if ($minPrice === null || $price < $minPrice) {
                $minPrice = $price;
            }
            $skuList[] = [
                'id'             => (int) $line->id,
                'product_sku_id' => (int) $line->product_sku_id,
                'spec_label'     => (string) ($ps->spec_label ?? ''),
                'price'          => $price,
                'stock'          => $stock,
            ];
        }

        $cat = $vp->shopCategory;
        $shopCategory = null;
        if ($cat && (int) ($cat->status ?? 0) === 1) {
            $shopCategory = [
                'id'   => (int) $cat->id,
                'name' => (string) ($cat->name ?? ''),
            ];
        }

        $pa = $product->toArray();
        $data = [
            'venue_product_id' => (int) $vp->id,
            'shop_category'    => $shopCategory,
            'product'          => [
                'id'          => (int) $product->id,
                'name'        => (string) ($pa['name'] ?? ''),
                'intro'       => (string) ($pa['intro'] ?? ''),
                'cover_image' => $pa['cover_image'] ?? '',
                'images'      => $this->decodeImages($pa['images'] ?? null),
                'detail'      => (string) ($pa['detail'] ?? ''),
                'unit'        => (string) ($pa['unit'] ?? '件'),
            ],
            'skus'             => $skuList,
            'min_price'        => $minPrice,
        ];

        return json(['code' => 0, 'msg' => 'success', 'data' => $data]);
    }

    private function isVenueVisible(int $venueId): bool
    {
        if ($venueId < 1) {
            return false;
        }
        $v = FishingVenue::find($venueId);

        return $v && (int) ($v->status ?? 0) === 1;
    }

    private function venueProductToListItem(VenueProduct $vp): array
    {
        $p = $vp->product;
        $cat = $vp->shopCategory;

        $shopCategory = null;
        if ($cat && (int) ($cat->status ?? 0) === 1) {
            $shopCategory = ['id' => (int) $cat->id, 'name' => (string) ($cat->name ?? '')];
        }

        $cover = '';
        $name = '';
        $intro = '';
        $unit = '件';
        $productId = 0;
        if ($p) {
            $pa = $p->toArray();
            $productId = (int) $p->id;
            $name = (string) ($pa['name'] ?? '');
            $intro = (string) ($pa['intro'] ?? '');
            $cover = (string) ($pa['cover_image'] ?? '');
            $unit = (string) ($pa['unit'] ?? '件');
        }

        $minPrice = null;
        $skuCount = 0;
        $lines = VenueProductSku::with(['productSku'])
            ->where('venue_product_id', (int) $vp->id)
            ->where('status', 1)
            ->select();
        foreach ($lines as $line) {
            $ps = $line->productSku;
            if (!$ps || (int) ($ps->status ?? 0) !== 1) {
                continue;
            }
            ++$skuCount;
            $pr = round((float) ($line->price ?? 0), 2);
            if ($minPrice === null || $pr < $minPrice) {
                $minPrice = $pr;
            }
        }

        return [
            'venue_product_id' => (int) $vp->id,
            'product_id'       => $productId,
            'shop_category_id' => $vp->shop_category_id !== null ? (int) $vp->shop_category_id : null,
            'shop_category'    => $shopCategory,
            'name'             => $name,
            'intro'            => $intro,
            'cover_image'      => $cover,
            'unit'             => $unit,
            'min_price'        => $minPrice,
            'sku_count'        => $skuCount,
        ];
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
}
