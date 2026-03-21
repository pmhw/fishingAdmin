<?php
declare(strict_types=1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\Product;
use app\model\ProductCategory;
use app\model\ProductSku;
use think\db\exception\DbException;
use think\response\Json;

/**
 * 后台 - 公共商品库（SPU / SKU）
 */
class ShopProductController extends BaseController
{
    /**
     * 分类下拉 GET /api/admin/shop/product-categories
     */
    public function categories(): Json
    {
        $rows = ProductCategory::where('status', 1)->order('sort_order', 'asc')->order('id', 'asc')->select();
        $list = [];
        foreach ($rows as $r) {
            $list[] = ['id' => (int) $r->id, 'name' => (string) ($r->name ?? '')];
        }
        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list]]);
    }

    /**
     * 列表 GET /api/admin/shop/products
     */
    public function list(): Json
    {
        $page = max((int) $this->request->get('page', 1), 1);
        $limit = min(max((int) $this->request->get('limit', 10), 1), 100);
        $keyword = trim((string) $this->request->get('keyword', ''));
        $status = $this->request->get('status');
        $categoryId = $this->request->get('category_id');

        $query = Product::order('sort_order', 'asc')->order('id', 'desc');
        if ($keyword !== '') {
            $query->whereLike('name', '%' . addcslashes($keyword, '%_\\') . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($categoryId !== null && $categoryId !== '') {
            $query->where('category_id', (int) $categoryId);
        }

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $items = [];
        foreach ($paginator->items() as $row) {
            $items[] = $this->productToListItem($row);
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => ['list' => $items, 'total' => $paginator->total()],
        ]);
    }

    /**
     * 详情（含 SKU）GET /api/admin/shop/products/:id
     */
    public function detail(int $id): Json
    {
        $product = Product::with(['skus'])->find($id);
        if (!$product) {
            return json(['code' => 404, 'msg' => '商品不存在', 'data' => null]);
        }
        return json(['code' => 0, 'msg' => 'success', 'data' => $this->productToDetail($product)]);
    }

    /**
     * 新增 POST /api/admin/shop/products
     */
    public function create(): Json
    {
        $name = trim((string) $this->request->post('name', ''));
        if ($name === '') {
            return json(['code' => 400, 'msg' => '请填写商品名称', 'data' => null]);
        }
        $row = Product::create([
            'category_id'  => (int) $this->request->post('category_id', 0),
            'name'         => $name,
            'intro'        => $this->nullableString($this->request->post('intro')),
            'cover_image'  => $this->nullableString($this->request->post('cover_image')),
            'images'       => $this->encodeImages($this->request->post('images')),
            'detail'       => $this->nullableString($this->request->post('detail')),
            'unit'         => trim((string) $this->request->post('unit', '件')) ?: '件',
            'status'       => (int) $this->request->post('status', 1),
            'sort_order'   => (int) $this->request->post('sort_order', 0),
        ]);

        return json(['code' => 0, 'msg' => '创建成功', 'data' => $this->productToDetail($row)]);
    }

    /**
     * 更新 PUT /api/admin/shop/products/:id
     */
    public function update(int $id): Json
    {
        /** @var Product|null $row */
        $row = Product::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '商品不存在', 'data' => null]);
        }
        $data = [];
        $name = $this->request->param('name');
        if ($name !== null && trim((string) $name) !== '') {
            $data['name'] = trim((string) $name);
        }
        foreach (['intro', 'cover_image', 'detail'] as $f) {
            $v = $this->request->param($f);
            if ($v !== null) {
                $data[$f] = $v === '' ? null : (string) $v;
            }
        }
        if ($this->request->param('images') !== null) {
            $data['images'] = $this->encodeImages($this->request->param('images'));
        }
        foreach (['category_id', 'status', 'sort_order'] as $f) {
            $v = $this->request->param($f);
            if ($v !== null && $v !== '') {
                $data[$f] = (int) $v;
            }
        }
        $unit = $this->request->param('unit');
        if ($unit !== null) {
            $data['unit'] = trim((string) $unit) ?: '件';
        }
        if (!empty($data)) {
            $row->save($data);
        }
        $row = Product::with(['skus'])->find($id);

        return json(['code' => 0, 'msg' => '保存成功', 'data' => $this->productToDetail($row)]);
    }

    /**
     * 删除 DELETE /api/admin/shop/products/:id
     */
    public function delete(int $id): Json
    {
        $row = Product::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '商品不存在', 'data' => null]);
        }
        try {
            $row->delete();
        } catch (DbException $e) {
            return json(['code' => 500, 'msg' => '删除失败：' . $e->getMessage(), 'data' => null]);
        }
        return json(['code' => 0, 'msg' => '已删除', 'data' => null]);
    }

    /**
     * 新增规格 POST /api/admin/shop/products/:id/skus
     */
    public function addSku(int $id): Json
    {
        $product = Product::find($id);
        if (!$product) {
            return json(['code' => 404, 'msg' => '商品不存在', 'data' => null]);
        }
        $specLabel = trim((string) $this->request->post('spec_label', ''));
        if ($specLabel === '') {
            return json(['code' => 400, 'msg' => '请填写规格名称', 'data' => null]);
        }
        $sku = ProductSku::create([
            'product_id'     => $id,
            'spec_label'     => $specLabel,
            'spec_json'      => $this->normalizeSpecJsonForDb($this->request->post('spec_json')),
            'sku_code'       => $this->nullableString($this->request->post('sku_code')),
            'default_price'  => $this->nullableDecimal($this->request->post('default_price')),
            'sort_order'     => (int) $this->request->post('sort_order', 0),
            'status'         => (int) $this->request->post('status', 1),
        ]);

        return json(['code' => 0, 'msg' => '规格已添加', 'data' => $this->skuToApi($sku)]);
    }

    /**
     * 更新规格 PUT /api/admin/shop/skus/:id
     */
    public function updateSku(int $id): Json
    {
        /** @var ProductSku|null $sku */
        $sku = ProductSku::find($id);
        if (!$sku) {
            return json(['code' => 404, 'msg' => '规格不存在', 'data' => null]);
        }
        $data = [];
        $specLabel = $this->request->param('spec_label');
        if ($specLabel !== null && trim((string) $specLabel) !== '') {
            $data['spec_label'] = trim((string) $specLabel);
        }
        if ($this->request->param('spec_json') !== null) {
            $data['spec_json'] = $this->normalizeSpecJsonForDb($this->request->param('spec_json'));
        }
        $skuCode = $this->request->param('sku_code');
        if ($skuCode !== null) {
            $data['sku_code'] = $skuCode === '' ? null : (string) $skuCode;
        }
        $dp = $this->request->param('default_price');
        if ($dp !== null && $dp !== '') {
            $data['default_price'] = round((float) $dp, 2);
        } elseif ($dp === '') {
            $data['default_price'] = null;
        }
        foreach (['sort_order', 'status'] as $f) {
            $v = $this->request->param($f);
            if ($v !== null && $v !== '') {
                $data[$f] = (int) $v;
            }
        }
        if (!empty($data)) {
            $sku->save($data);
            $sku = ProductSku::find($id);
        }

        return json(['code' => 0, 'msg' => '保存成功', 'data' => $this->skuToApi($sku)]);
    }

    /**
     * 删除规格 DELETE /api/admin/shop/skus/:id
     */
    public function deleteSku(int $id): Json
    {
        $sku = ProductSku::find($id);
        if (!$sku) {
            return json(['code' => 404, 'msg' => '规格不存在', 'data' => null]);
        }
        try {
            $sku->delete();
        } catch (DbException $e) {
            return json(['code' => 500, 'msg' => '删除失败：可能已被店铺引用', 'data' => null]);
        }
        return json(['code' => 0, 'msg' => '已删除', 'data' => null]);
    }

    private function productToListItem(Product $row): array
    {
        $arr = $row->toArray();
        $arr['images'] = $this->decodeImages($arr['images'] ?? null);
        $arr['sku_count'] = (int) ProductSku::where('product_id', (int) $row->id)->count();

        return $arr;
    }

    private function productToDetail(?Product $row): ?array
    {
        if (!$row) {
            return null;
        }
        $arr = $row->toArray();
        $arr['images'] = $this->decodeImages($arr['images'] ?? null);
        $skus = [];
        foreach ($row->skus ?? [] as $s) {
            $skus[] = $this->skuToApi($s);
        }
        $arr['skus'] = $skus;

        return $arr;
    }

    private function skuToApi(ProductSku $s): array
    {
        $a = $s->toArray();
        $a['spec_json'] = $this->decodeSpecJson($a['spec_json'] ?? null);

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

    private function encodeImages(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_string($v)) {
            return $v;
        }
        if (!is_array($v)) {
            return null;
        }

        return json_encode(array_values($v), JSON_UNESCAPED_UNICODE);
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

    private function normalizeSpecJsonForDb(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_array($v)) {
            return json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        if (is_string($v)) {
            $t = trim($v);
            if ($t === '') {
                return null;
            }
            $j = json_decode($t, true);
            if (is_array($j)) {
                return json_encode($j, JSON_UNESCAPED_UNICODE);
            }

            return $t;
        }

        return null;
    }

    private function nullableString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function nullableDecimal(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return round((float) $v, 2);
    }
}
