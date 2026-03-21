<?php
declare(strict_types=1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\Product;
use app\model\ProductSku;
use think\db\exception\DbException;
use think\response\Json;

/**
 * 后台 - 公共商品库（SPU / SKU）
 */
class ShopProductController extends BaseController
{
    /**
     * 列表 GET /api/admin/shop/products
     */
    public function list(): Json
    {
        $page = max((int) $this->request->get('page', 1), 1);
        $limit = min(max((int) $this->request->get('limit', 10), 1), 100);
        $keyword = trim((string) $this->request->get('keyword', ''));
        $status = $this->request->get('status');

        $query = Product::order('sort_order', 'asc')->order('id', 'desc');
        if ($keyword !== '') {
            $query->whereLike('name', '%' . addcslashes($keyword, '%_\\') . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
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
            'category_id'  => 0,
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
        foreach (['status', 'sort_order'] as $f) {
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

    private function nullableString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

}
