<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>池塘列表</span>
          <el-button type="primary" @click="openEdit()">添加池塘</el-button>
        </div>
      </template>
      <el-form inline class="filter-form">
        <el-form-item label="钓场">
          <el-select v-model="filterVenueId" placeholder="全部" clearable style="width: 180px" @change="fetchList">
            <el-option v-for="v in venueOptions" :key="v.id" :label="v.name" :value="v.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="venue_name" label="所属钓场" min-width="120" show-overflow-tooltip />
        <el-table-column prop="name" label="池塘名称" min-width="120" show-overflow-tooltip />
        <el-table-column label="类型" width="90">
          <template #default="{ row }">{{ pondTypeLabel(row.pond_type) }}</template>
        </el-table-column>
        <el-table-column prop="seat_count" label="钓位数" width="90" />
        <el-table-column prop="area_mu" label="面积(亩)" width="90" />
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'open' ? 'success' : 'info'" size="small">
              {{ row.status === 'open' ? '开放' : '关闭' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="70" />
        <el-table-column prop="created_at" label="创建时间" width="170" />
        <el-table-column label="操作" fixed="right" width="260">
          <template #default="{ row }">
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button link type="primary" @click="openRegionConfig(row)">钓位配置</el-button>
            <el-button link type="primary" @click="openFeeConfig(row)">收费规则</el-button>
            <el-button link type="primary" @click="openReturnConfig(row)">回鱼规则</el-button>
            <el-button link type="primary" @click="openFeedConfig(row)">放鱼记录</el-button>
            <el-button v-if="canDeletePonds" link type="danger" @click="onDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-pagination
        :current-page="page"
        :page-size="limit"
        :total="total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next"
        style="margin-top: 16px"
        @current-change="(p) => { page = p; fetchList(); }"
        @size-change="(s) => { limit = s; page = 1; fetchList(); }"
      />
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="editId ? '编辑池塘' : '添加池塘'"
      width="560px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @close="resetForm"
    >
      <el-form ref="formRef" :model="editForm" :rules="editRules" label-width="100px">
        <el-form-item label="所属钓场" prop="venue_id">
          <el-select v-model="editForm.venue_id" placeholder="请选择钓场" style="width:100%" :disabled="!!editId">
            <el-option v-for="v in venueOptions" :key="v.id" :label="v.name" :value="v.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="池塘名称" prop="name">
          <el-input v-model="editForm.name" placeholder="必填" />
        </el-form-item>
        <el-form-item label="池塘类型">
          <el-select v-model="editForm.pond_type" placeholder="请选择" style="width:100%">
            <el-option label="黑坑" value="black_pit" />
            <el-option label="斤塘" value="jin_tang" />
            <el-option label="练杆塘" value="practice" />
          </el-select>
        </el-form-item>
        <el-form-item label="池塘图片">
          <div class="upload-wrap">
            <el-image
              v-if="coverImageUrl"
              :src="coverImageUrl"
              :preview-src-list="[coverImageUrl]"
              preview-teleported
              fit="cover"
              class="upload-preview"
            />
            <el-upload :show-file-list="false" accept="image/*" :http-request="handleImageUpload">
              <el-button type="primary" :loading="uploading">{{ coverImageUrl ? '更换' : '上传' }}</el-button>
            </el-upload>
          </div>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="钓位数">
              <el-input-number v-model="editForm.seat_count" :min="0" controls-position="right" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="面积(亩)">
              <el-input-number v-model="editForm.area_mu" :min="0" :precision="2" controls-position="right" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="水深">
          <el-input v-model="editForm.water_depth" placeholder="如 1.5-2米" />
        </el-form-item>
        <el-form-item label="鱼种">
          <el-input v-model="editForm.fish_species" placeholder="逗号分隔，如 鲫鱼,鲤鱼" />
        </el-form-item>
        <el-form-item label="限杆规则">
          <el-input v-model="editForm.rod_rule" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
        <el-form-item label="限饵规则">
          <el-input v-model="editForm.bait_rule" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
        <el-form-item label="补充规则">
          <el-input v-model="editForm.extra_rule" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
        <el-form-item label="开塘时间">
          <el-date-picker v-model="editForm.open_time" type="date" value-format="YYYY-MM-DD" placeholder="选填" style="width:100%" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="editForm.status">
            <el-radio value="open">开放</el-radio>
            <el-radio value="closed">关闭</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="editForm.sort_order" :min="0" controls-position="right" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitEdit">确定</el-button>
      </template>
    </el-dialog>

    <!-- 钓位区域配置（独立弹窗） -->
    <el-dialog
      v-model="regionConfigVisible"
      :title="'钓位区域配置 - ' + (regionConfigPondName || '')"
      width="520px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @close="closeRegionConfig"
    >
      <div v-loading="regionListLoading" class="region-config-body">
        <div class="region-toolbar">
          <span class="region-tip">如 西岸1~29、中间浮桥30~89，可添加多段序号范围</span>
          <div class="region-toolbar-actions">
            <el-button type="success" size="small" :loading="seatSyncLoading" @click="onSyncSeats">生成座位/二维码码值</el-button>
            <el-button type="primary" size="small" :loading="seatZipLoading" @click="onDownloadSeatZip">下载二维码ZIP</el-button>
            <el-button type="danger" size="small" plain :loading="seatCleanupLoading" @click="onCleanupSeatQrs">清理二维码</el-button>
            <el-button type="primary" size="small" @click="openRegionForm">添加区域</el-button>
          </div>
        </div>
        <el-table :data="regionList" size="small" max-height="220" stripe class="region-table">
          <el-table-column prop="name" label="区域名称" min-width="120" />
          <el-table-column label="钓位序号" width="160">
            <template #default="{ row }">{{ row.start_no }} ~ {{ row.end_no }}</template>
          </el-table-column>
          <el-table-column label="操作" width="80" fixed="right">
            <template #default="{ row }">
              <el-button link type="danger" size="small" @click="onDeleteRegion(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div v-if="seatNumbersByRegion.length" class="seat-numbers-section">
          <div class="seat-numbers-label">座位号排列（按区域）</div>
          <div v-for="rg in seatNumbersByRegion" :key="rg.key" class="seat-region">
            <div class="seat-region-head">
              <span class="seat-region-name">{{ rg.name }}</span>
              <span class="seat-region-meta">{{ rg.start }} ~ {{ rg.end }}（{{ rg.seats.length }}个）</span>
            </div>
            <div class="seat-numbers-wrap">
              <el-tooltip v-for="n in rg.seats" :key="rg.key + '-' + n" :content="seatCodeMap[n] ? ('code: ' + seatCodeMap[n]) : '未生成 code'" placement="top">
                <span class="seat-num">
                  <span class="seat-icon" aria-hidden="true">
                    <svg class="seat-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" width="20" height="20" style="display:inline-block;vertical-align:middle;fill:#606266">
                      <path fill="#606266" d="M765.449565 1024c-5.847252 0-10.963597-5.116345-10.963598-10.963597V928.982156c0-5.847252 5.116345-10.963597 10.963598-10.963598s10.963597 5.116345 10.963597 10.963598v84.054247c-0.730906 5.847252-5.116345 10.963597-10.963597 10.963597zM685.780757 997.687366c-5.847252 0-10.963597-5.116345-10.963598-10.963597v-57.010707c0-5.847252 5.116345-10.963597 10.963598-10.963597s10.963597 5.116345 10.963597 10.963597v57.010707c-0.730906 5.847252-5.116345 10.963597-10.963597 10.963597zM258.200457 1024c-5.847252 0-10.963597-5.116345-10.963598-10.963597V928.982156c0-5.847252 5.116345-10.963597 10.963598-10.963598s10.963597 5.116345 10.963597 10.963598v84.054247c0 5.847252-5.116345 10.963597-10.963597 10.963597zM337.869265 997.687366c-5.847252 0-10.963597-5.116345-10.963598-10.963597v-57.010707c0-5.847252 5.116345-10.963597 10.963598-10.963597s10.963597 5.116345 10.963597 10.963597v57.010707c0 5.847252-5.116345 10.963597-10.963597 10.963597z"/>
                      <path fill="#606266" d="M785.914947 939.945753h-548.179872c-42.392577 0-77.476089-35.083512-77.476089-77.476088v-182.726624C117.135503 676.819415 77.666553 658.546752 47.699386 627.117773 14.808594 592.765168-2.002255 548.179872 0.190464 500.670949c4.385439-86.977873 75.283369-158.606709 162.992148-162.992148 47.508922-2.192719 92.094218 14.61813 126.446824 46.778016 34.352605 32.890792 52.625268 76.745182 52.625268 123.523197v181.995718h339.140614V508.710921c0-47.508922 19.003569-91.363312 52.625267-123.523198 34.352605-32.890792 78.937901-48.970735 126.446824-46.778016 86.977873 4.385439 158.606709 75.283369 162.992149 162.992149 2.192719 47.508922-14.61813 92.094218-47.508923 126.446824-29.967166 31.428979-70.167024 49.701642-113.290506 52.625267v182.726624c0.730906 42.392577-34.352605 76.745182-76.745182 76.745182zM171.222584 359.605996h-7.309065c-76.014276 3.654532-138.872234 65.781585-141.79586 142.526766-2.192719 41.66167 12.42541 80.399714 40.930764 110.366881 28.505353 29.967166 67.243398 46.047109 108.174161 46.047109 5.847252 0 10.963597 5.116345 10.963597 10.963598v192.959315c0 30.698073 24.850821 56.2798 56.2798 56.2798h547.448966c30.698073 0 56.2798-24.850821 56.2798-56.2798V669.51035c0-5.847252 5.116345-10.963597 10.963597-10.963598 40.930764 0 79.668808-16.810849 108.174161-46.047109 28.505353-29.967166 43.123483-68.705211 40.930764-110.366881-3.654532-76.014276-65.781585-138.872234-142.526766-142.526766-41.66167-2.192719-80.399714 12.42541-110.366881 40.930763-29.967166 28.505353-46.047109 67.243398-46.047109 108.174162v192.959315c0 5.847252-5.116345 10.963597-10.963598 10.963597H331.291106c-5.847252 0-10.963597-5.116345-10.963597-10.963597V508.710921c0-40.930764-16.079943-79.668808-46.047109-108.174162-27.774447-26.312634-64.319772-40.930764-103.057816-40.930763z"/>
                      <path fill="#606266" d="M691.628009 712.633833H331.291106c-5.847252 0-10.963597-5.116345-10.963597-10.963597V508.710921c0-82.592434-67.243398-149.835832-149.835832-149.835832-5.847252 0-10.963597-5.116345-10.963597-10.963597v-248.508209C159.52808 44.585296 204.113376 0 258.931363 0h502.863669c54.817987 0 99.403283 44.585296 99.403284 99.403283v249.239115c0 5.847252-5.116345 10.963597-10.963598 10.963598-82.592434 0-149.835832 67.243398-149.835831 149.835831V702.401142c2.192719 5.116345-2.192719 10.232691-8.770878 10.232691z m-349.373305-21.927195h339.140614V508.710921c0-90.632405 70.89793-165.184868 160.068522-171.03212V99.403283c0-43.123483-35.083512-78.206995-78.206995-78.206995H260.393176c-43.123483 0-78.206995 35.083512-78.206995 78.206995v239.006424c89.170592 5.847252 160.068522 79.668808 160.068523 171.03212v181.264811z"/>
                    </svg>
                  </span>
                  <span class="seat-no">{{ n }}</span>
                </span>
              </el-tooltip>
            </div>
          </div>
        </div>
      </div>
    </el-dialog>

    <el-dialog
      v-model="regionDialogVisible"
      title="添加钓位区域"
      width="400px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @close="resetRegionForm"
    >
      <el-form ref="regionFormRef" :model="regionForm" :rules="regionRules" label-width="100px">
        <el-form-item label="区域名称" prop="name">
          <el-input v-model="regionForm.name" placeholder="如 西岸、中间浮桥" />
        </el-form-item>
        <el-form-item label="起始序号" prop="start_no">
          <el-input-number v-model="regionForm.start_no" :min="0" controls-position="right" style="width:100%" />
        </el-form-item>
        <el-form-item label="结束序号" prop="end_no">
          <el-input-number v-model="regionForm.end_no" :min="0" controls-position="right" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="regionDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="regionSubmitLoading" @click="submitRegion">确定</el-button>
      </template>
    </el-dialog>

    <!-- 收费规则配置 -->
    <el-dialog
      v-model="feeConfigVisible"
      :title="'收费规则 - ' + (feeConfigPondName || '')"
      width="640px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @close="closeFeeConfig"
    >
      <div v-loading="feeListLoading" class="region-config-body">
        <div class="region-toolbar">
          <span class="region-tip">如 正钓4小时、偷驴1天等</span>
          <el-button type="primary" size="small" @click="openFeeForm">添加规则</el-button>
        </div>
        <el-table :data="feeList" size="small" max-height="280" stripe class="region-table">
          <el-table-column prop="name" label="收费名称" min-width="120" />
          <el-table-column label="时长" width="120">
            <template #default="{ row }">
              {{ row.duration || (row.duration_value != null ? `${row.duration_value}${row.duration_unit === 'day' ? '天' : '小时'}` : '-') }}
            </template>
          </el-table-column>
          <el-table-column label="金额(元)" width="100">
            <template #default="{ row }">{{ row.amount }}</template>
          </el-table-column>
          <el-table-column label="押金(元)" width="90">
            <template #default="{ row }">{{ row.deposit ?? 0 }}</template>
          </el-table-column>
          <el-table-column prop="sort_order" label="排序" width="70" />
          <el-table-column label="操作" width="120" fixed="right">
            <template #default="{ row }">
              <el-button link type="primary" size="small" @click="openFeeForm(row)">编辑</el-button>
              <el-button link type="danger" size="small" @click="onDeleteFeeRule(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-dialog>

    <el-dialog
      v-model="feeDialogVisible"
      :title="feeEditId ? '编辑收费规则' : '添加收费规则'"
      width="440px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @close="resetFeeForm"
    >
      <div v-loading="feeSubmitLoading" element-loading-text="提交中…" class="fee-form-wrap">
        <el-form ref="feeFormRef" :model="feeForm" :rules="feeRules" label-width="90px">
          <el-form-item label="收费名称" prop="name">
            <el-input v-model="feeForm.name" placeholder="如 正钓4小时" />
          </el-form-item>
          <el-form-item label="垂钓时长" prop="duration_value">
            <div class="duration-with-unit">
              <el-input-number v-model="feeForm.duration_value" :min="0" :precision="2" controls-position="right" placeholder="数值" style="width: 140px" />
              <el-select v-model="feeForm.duration_unit" placeholder="单位" style="width: 100px">
                <el-option label="小时" value="hour" />
                <el-option label="天" value="day" />
              </el-select>
            </div>
          </el-form-item>
          <el-form-item label="金额(元)" prop="amount">
            <el-input-number v-model="feeForm.amount" :min="0" :precision="2" controls-position="right" style="width:100%" />
          </el-form-item>
          <el-form-item label="押金(元)" prop="deposit">
            <el-input-number v-model="feeForm.deposit" :min="0" :precision="2" controls-position="right" style="width:100%" />
          </el-form-item>
          <el-form-item label="排序" prop="sort_order">
            <el-input-number v-model="feeForm.sort_order" :min="0" controls-position="right" style="width:100%" />
          </el-form-item>
        </el-form>
      </div>
      <template #footer>
        <el-button :disabled="feeSubmitLoading" @click="feeDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="feeSubmitLoading" @click="submitFee">确定</el-button>
      </template>
    </el-dialog>

    <!-- 回鱼规则配置：横向表格，表内输入 + 行内保存/删除，顶部添加 -->
    <el-dialog
      v-model="returnConfigVisible"
      :title="'回鱼规则 - ' + (returnConfigPondName || '')"
      width="900px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @close="closeReturnConfig"
    >
      <div v-loading="returnListLoading" class="return-config-wrap">
        <div class="region-toolbar">
          <span class="region-tip">条数范围内按斤/按条回鱼，0 表示不限</span>
          <el-button type="primary" size="small" @click="addReturnRow">添加</el-button>
        </div>
        <el-table :data="returnDisplayList" size="small" max-height="400" stripe class="return-inline-table">
          <el-table-column label="规则名称" min-width="120">
            <template #default="{ row }">
              <el-input v-model="row.name" placeholder="如 鲫鱼回鱼" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="下限(条)" width="100">
            <template #default="{ row }">
              <el-input-number v-model="row.lower_limit" :min="0" controls-position="right" size="small" style="width: 100%" />
            </template>
          </el-table-column>
          <el-table-column label="上限(条)" width="100">
            <template #default="{ row }">
              <el-input-number v-model="row.upper_limit" :min="0" controls-position="right" size="small" style="width: 100%" />
            </template>
          </el-table-column>
          <el-table-column label="回鱼方式" width="110">
            <template #default="{ row }">
              <el-select v-model="row.return_type" placeholder="方式" size="small" style="width: 100%">
                <el-option label="按斤" value="jin" />
                <el-option label="按条" value="tiao" />
              </el-select>
            </template>
          </el-table-column>
          <el-table-column label="金额(元)" width="100">
            <template #default="{ row }">
              <el-input-number v-model="row.amount" :min="0" :precision="2" controls-position="right" size="small" style="width: 100%" />
            </template>
          </el-table-column>
          <el-table-column label="排序" width="80">
            <template #default="{ row }">
              <el-input-number v-model="row.sort_order" :min="0" controls-position="right" size="small" style="width: 100%" />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="140" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" link size="small" :loading="savingReturnKey === getReturnRowKey(row)" @click="saveReturnRow(row)">保存</el-button>
              <el-button type="danger" link size="small" @click="deleteReturnRow(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-dialog>

    <!-- 放鱼记录管理 -->
    <el-dialog
      v-model="feedConfigVisible"
      :title="'放鱼记录 - ' + (feedConfigPondName || '')"
      width="760px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @close="closeFeedConfig"
    >
      <div v-loading="feedListLoading" class="region-config-body">
        <div class="region-toolbar">
          <span class="region-tip">记录每次放鱼的时间、说明和图片</span>
          <el-button type="primary" size="small" @click="openFeedForm()">新增记录</el-button>
        </div>
        <el-table :data="feedList" size="small" max-height="320" stripe>
          <el-table-column prop="feed_time" label="放鱼时间" width="160" />
          <el-table-column prop="title" label="标题" min-width="120" show-overflow-tooltip />
          <el-table-column label="说明" min-width="200" show-overflow-tooltip>
            <template #default="{ row }">
              {{ row.content }}
            </template>
          </el-table-column>
          <el-table-column label="图片" width="120">
            <template #default="{ row }">
              <el-image
                v-if="row.images && row.images.length"
                :src="formatStorageUrl(row.images[0])"
                :preview-src-list="row.images.map(formatStorageUrl)"
                preview-teleported
                fit="cover"
                style="width:60px;height:60px;border-radius:4px"
              />
              <span v-else style="color:#999">-</span>
            </template>
          </el-table-column>
          <el-table-column prop="sort_order" label="排序" width="70" />
          <el-table-column label="操作" width="140" fixed="right">
            <template #default="{ row }">
              <el-button link type="primary" size="small" @click="openFeedForm(row)">编辑</el-button>
              <el-button link type="danger" size="small" @click="onDeleteFeed(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-dialog>

    <!-- 放鱼记录表单 -->
    <el-dialog
      v-model="feedDialogVisible"
      :title="feedEditId ? '编辑放鱼记录' : '新增放鱼记录'"
      width="540px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @close="resetFeedForm"
    >
      <div v-loading="feedSubmitLoading" element-loading-text="提交中…" class="fee-form-wrap">
        <el-form ref="feedFormRef" :model="feedForm" :rules="feedRules" label-width="90px">
          <el-form-item label="标题" prop="title">
            <el-input v-model="feedForm.title" placeholder="如 今日放鱼公告（可选）" />
          </el-form-item>
          <el-form-item label="放鱼时间" prop="feed_time">
            <el-date-picker
              v-model="feedForm.feed_time"
              type="datetime"
              value-format="YYYY-MM-DD HH:mm:ss"
              placeholder="选填，不填则按创建时间"
              style="width: 100%"
            />
          </el-form-item>
          <el-form-item label="说明" prop="content">
            <el-input v-model="feedForm.content" type="textarea" :rows="3" placeholder="本次放鱼说明，如 品种、数量等" />
          </el-form-item>
          <el-form-item label="图片">
            <div class="upload-multi-wrap">
              <div class="thumb-list">
                <div
                  v-for="(img, idx) in feedForm.images"
                  :key="idx"
                  class="thumb-item"
                >
                  <el-image
                    :src="img.preview || formatStorageUrl(img.url)"
                    :preview-src-list="feedForm.images.map((i) => i.preview || formatStorageUrl(i.url))"
                    preview-teleported
                    fit="cover"
                    style="width:86px;height:86px;border-radius:4px"
                  />
                  <span class="thumb-remove" @click="removeFeedImage(idx)">×</span>
                </div>
                <!-- 仿微信朋友圈：最后一个是 + 卡片，达到上限后不显示；仅选择文件，不立即上传 -->
                <el-upload
                  v-if="feedForm.images.length < 9"
                  class="thumb-item upload-add-card"
                  :show-file-list="false"
                  accept="image/*"
                  :auto-upload="false"
                  :on-change="handleFeedFileChange"
                >
                  <div class="upload-add-inner">
                    <span class="upload-add-icon">＋</span>
                    <span class="upload-add-text">添加图片</span>
                  </div>
                </el-upload>
              </div>
            </div>
          </el-form-item>
          <el-form-item label="排序" prop="sort_order">
            <el-input-number v-model="feedForm.sort_order" :min="0" controls-position="right" style="width:100%" />
          </el-form-item>
        </el-form>
      </div>
      <template #footer>
        <el-button :disabled="feedSubmitLoading" @click="feedDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="feedSubmitLoading" @click="submitFeed">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getPondList,
  getPondDetail,
  createPond,
  updatePond,
  deletePond,
  getVenueOptions,
  getPondRegions,
  createPondRegion,
  deletePondRegion,
  getPondFeeRules,
  createPondFeeRule,
  updatePondFeeRule,
  deletePondFeeRule,
  getPondReturnRules,
  createPondReturnRule,
  updatePondReturnRule,
  deletePondReturnRule,
  getPondFeedLogs,
  createPondFeedLog,
  updatePondFeedLog,
  deletePondFeedLog,
  getPondSeats,
  syncPondSeats,
  downloadPondSeatQrsZip,
  cleanupPondSeatQrs,
  uploadImage,
} from '@/api/pond'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)
const filterVenueId = ref('')
const venueOptions = ref([])
/** 仅「全部池塘」权限时为 true，可显示删除按钮 */
const canDeletePonds = ref(false)

const dialogVisible = ref(false)
const editId = ref(null)
const formRef = ref(null)
const submitLoading = ref(false)
const uploading = ref(false)

const regionList = ref([])
const regionDialogVisible = ref(false)
const regionFormRef = ref(null)
const regionSubmitLoading = ref(false)
const regionForm = reactive({ name: '', start_no: 0, end_no: 0 })
/** 钓位配置独立弹窗 */
const regionConfigVisible = ref(false)
const regionConfigPondId = ref(null)
const regionConfigPondName = ref('')
const regionListLoading = ref(false)
const seatSyncLoading = ref(false)
const seatZipLoading = ref(false)
const seatCleanupLoading = ref(false)
const seatCodeMap = ref({})
const regionRules = {
  name: [{ required: true, message: '请输入区域名称', trigger: 'blur' }],
}

/** 收费规则弹窗 */
const feeConfigVisible = ref(false)
const feeConfigPondId = ref(null)
const feeConfigPondName = ref('')
const feeList = ref([])
const feeListLoading = ref(false)
const feeDialogVisible = ref(false)
const feeEditId = ref(null)
const feeFormRef = ref(null)
const feeSubmitLoading = ref(false)
const feeForm = reactive({
  name: '',
  duration_value: 0,
  duration_unit: 'hour',
  amount: 0,
  deposit: 0,
  sort_order: 0,
})
const feeRules = {
  name: [{ required: true, message: '请输入收费名称', trigger: 'blur' }],
  duration_unit: [{ required: true, message: '请选择时长单位', trigger: 'change' }],
}

/** 回鱼规则弹窗：横向表内编辑 */
const returnConfigVisible = ref(false)
const returnConfigPondId = ref(null)
const returnConfigPondName = ref('')
const returnList = ref([])
const returnNewRows = ref([])
const returnListLoading = ref(false)
const savingReturnKey = ref(null)

const returnDisplayList = computed(() => [...returnList.value, ...returnNewRows.value])

function getReturnRowKey(row) {
  if (row._new && row._tid != null) return row._tid
  return row.id
}

const editForm = reactive({
  venue_id: null,
  name: '',
  images: '',
  pond_type: 'black_pit',
  seat_count: 0,
  area_mu: null,
  water_depth: '',
  fish_species: '',
  rod_rule: '',
  bait_rule: '',
  extra_rule: '',
  open_time: null,
  status: 'open',
  sort_order: 0,
})

const editRules = {
  venue_id: [{ required: true, message: '请选择所属钓场', trigger: 'change' }],
  name: [{ required: true, message: '请输入池塘名称', trigger: 'blur' }],
}

/** 放鱼记录弹窗 */
const feedConfigVisible = ref(false)
const feedConfigPondId = ref(null)
const feedConfigPondName = ref('')
const feedList = ref([])
const feedListLoading = ref(false)
const feedDialogVisible = ref(false)
const feedEditId = ref(null)
const feedFormRef = ref(null)
const feedSubmitLoading = ref(false)
const feedUploading = ref(false)
const feedForm = reactive({
  pond_id: null,
  title: '',
  content: '',
  /** images: { url: string, file?: File, preview?: string, isNew?: boolean }[] */
  images: [],
  feed_time: '',
  sort_order: 0,
})
const feedRules = {
  content: [{ required: true, message: '请输入放鱼说明', trigger: 'blur' }],
}

function pondTypeLabel(type) {
  const map = { black_pit: '黑坑', jin_tang: '斤塘', practice: '练杆塘' }
  return map[type] || type
}

function formatStorageUrl(u) {
  if (!u) return ''
  if (u.startsWith('http')) return u
  const base = import.meta.env.VITE_STORAGE_URL || ''
  return base + u
}

function openFeedConfig(row) {
  feedConfigPondId.value = row.id
  feedConfigPondName.value = row.name
  feedConfigVisible.value = true
  loadFeedList()
}

function closeFeedConfig() {
  feedConfigVisible.value = false
  feedConfigPondId.value = null
  feedConfigPondName.value = ''
  feedList.value = []
}

async function loadFeedList() {
  if (!feedConfigPondId.value) return
  try {
    feedListLoading.value = true
    const res = await getPondFeedLogs(feedConfigPondId.value)
    const data = res?.data ?? res
    feedList.value = data?.list || []
  } catch (e) {
    console.error(e)
    ElMessage.error('加载放鱼记录失败')
  } finally {
    feedListLoading.value = false
  }
}

function openFeedForm(row) {
  if (!feedConfigPondId.value) {
    ElMessage.error('请先选择池塘')
    return
  }
  if (row) {
    feedEditId.value = row.id
    feedForm.pond_id = row.pond_id
    feedForm.title = row.title || ''
    feedForm.content = row.content || ''
    feedForm.images = Array.isArray(row.images)
      ? row.images.map((u) => ({
          url: u,
          file: null,
          preview: formatStorageUrl(u),
          isNew: false,
        }))
      : []
    feedForm.feed_time = row.feed_time || ''
    feedForm.sort_order = row.sort_order ?? 0
  } else {
    feedEditId.value = null
    feedForm.pond_id = feedConfigPondId.value
    feedForm.title = ''
    feedForm.content = ''
    feedForm.images = []
    feedForm.feed_time = ''
    feedForm.sort_order = 0
  }
  feedDialogVisible.value = true
}

function resetFeedForm() {
  feedDialogVisible.value = false
  feedEditId.value = null
  if (feedFormRef.value) {
    feedFormRef.value.clearValidate()
  }
}

async function submitFeed() {
  if (!feedFormRef.value) return
  await feedFormRef.value.validate()
  try {
    feedSubmitLoading.value = true
    // 先把新增的本地文件统一上传，拿到线上 URL
    for (const img of feedForm.images) {
      if (img.isNew && img.file) {
        try {
          const res = await uploadImage(img.file)
          const url = res?.data?.url ?? res?.url ?? ''
          if (!url) {
            throw new Error('empty url')
          }
          img.url = url
          img.preview = formatStorageUrl(url)
          img.isNew = false
        } catch (e) {
          console.error(e)
          ElMessage.error('有图片上传失败，请稍后重试')
          feedSubmitLoading.value = false
          return
        }
      }
    }

    const payload = {
      pond_id: feedForm.pond_id,
      title: feedForm.title,
      content: feedForm.content,
      images: feedForm.images.map((img) => img.url).filter((u) => u),
      feed_time: feedForm.feed_time,
      sort_order: feedForm.sort_order,
    }
    if (feedEditId.value) {
      await updatePondFeedLog(feedEditId.value, payload)
      ElMessage.success('更新成功')
    } else {
      await createPondFeedLog(payload)
      ElMessage.success('添加成功')
    }
    feedDialogVisible.value = false
    loadFeedList()
  } catch (e) {
    console.error(e)
  } finally {
    feedSubmitLoading.value = false
  }
}

async function onDeleteFeed(row) {
  try {
    await ElMessageBox.confirm('确定删除该放鱼记录？', '提示', { type: 'warning' })
  } catch {
    return
  }
  try {
    await deletePondFeedLog(row.id)
    ElMessage.success('删除成功')
    loadFeedList()
  } catch (e) {
    console.error(e)
  }
}

function handleFeedFileChange(uploadFile) {
  const file = uploadFile.raw
  if (!file) return
  const preview = URL.createObjectURL(file)
  feedForm.images.push({
    url: '',
    file,
    preview,
    isNew: true,
  })
}

function removeFeedImage(idx) {
  if (!Array.isArray(feedForm.images)) return
  const img = feedForm.images[idx]
  if (img && img.preview && img.preview.startsWith('blob:')) {
    URL.revokeObjectURL(img.preview)
  }
  feedForm.images.splice(idx, 1)
}

const coverImageUrl = computed(() => {
  let urls = []
  try {
    urls = typeof editForm.images === 'string' ? (JSON.parse(editForm.images || '[]') || []) : (editForm.images || [])
  } catch (_) {
    urls = []
  }
  const u = Array.isArray(urls) && urls.length ? urls[0] : ''
  return u ? (u.startsWith('http') ? u : (import.meta.env.VITE_STORAGE_URL ? import.meta.env.VITE_STORAGE_URL + u : u)) : ''
})

/** 根据钓位区域起止序号展开为座位号列表（按区域分组展示） */
const seatNumbersByRegion = computed(() => {
  const list = Array.isArray(regionList.value) ? regionList.value : []
  return list
    .slice()
    .sort((a, b) => (Number(a.start_no) || 0) - (Number(b.start_no) || 0))
    .map((r) => {
      const start = Number(r.start_no) || 0
      const end = Number(r.end_no) || 0
      const min = Math.min(start, end)
      const max = Math.max(start, end)
      const seats = []
      for (let i = min; i <= max; i++) seats.push(i)
      return {
        key: String(r.id ?? `${r.name || 'region'}-${min}-${max}`),
        name: r.name || '未命名区域',
        start: min,
        end: max,
        seats,
      }
    })
    .filter((rg) => rg.seats.length > 0)
})

async function handleImageUpload({ file }) {
  if (!file) return
  uploading.value = true
  try {
    const res = await uploadImage(file)
    const url = res.data?.url ?? res?.url ?? ''
    if (url) editForm.images = JSON.stringify([url])
    ElMessage.success('上传成功')
  } catch (_) {
    ElMessage.error('上传失败')
  } finally {
    uploading.value = false
  }
}

async function loadVenueOptions() {
  try {
    const res = await getVenueOptions()
    const data = res?.data ?? res
    venueOptions.value = data?.list ?? []
  } catch (_) {
    venueOptions.value = []
  }
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getPondList({
      page: page.value,
      limit: limit.value,
      venue_id: filterVenueId.value || undefined,
    })
    const data = res?.data ?? res
    list.value = data?.list ?? []
    total.value = data?.total ?? 0
    canDeletePonds.value = data?.can_delete ?? false
  } finally {
    loading.value = false
  }
}

function openEdit(row) {
  editId.value = row?.id ?? null
  editForm.venue_id = row?.venue_id ?? null
  editForm.name = row?.name ?? ''
  editForm.images = row?.images ?? ''
  editForm.pond_type = row?.pond_type ?? 'black_pit'
  editForm.seat_count = row?.seat_count ?? 0
  editForm.area_mu = row?.area_mu ?? null
  editForm.water_depth = row?.water_depth ?? ''
  editForm.fish_species = row?.fish_species ?? ''
  editForm.rod_rule = row?.rod_rule ?? ''
  editForm.bait_rule = row?.bait_rule ?? ''
  editForm.extra_rule = row?.extra_rule ?? ''
  editForm.open_time = row?.open_time ?? null
  editForm.status = row?.status ?? 'open'
  editForm.sort_order = row?.sort_order ?? 0
  dialogVisible.value = true
  if (editId.value) {
    getPondDetail(editId.value).then((res) => {
      const d = res?.data ?? res
      if (d) {
        editForm.venue_id = d.venue_id ?? editForm.venue_id
        editForm.name = d.name ?? ''
        editForm.images = Array.isArray(d.images) ? JSON.stringify(d.images) : (d.images ?? '')
        editForm.pond_type = d.pond_type ?? 'black_pit'
        editForm.seat_count = d.seat_count ?? 0
        editForm.area_mu = d.area_mu ?? null
        editForm.water_depth = d.water_depth ?? ''
        editForm.fish_species = d.fish_species ?? ''
        editForm.rod_rule = d.rod_rule ?? ''
        editForm.bait_rule = d.bait_rule ?? ''
        editForm.extra_rule = d.extra_rule ?? ''
        editForm.open_time = d.open_time ?? null
        editForm.status = d.status ?? 'open'
        editForm.sort_order = d.sort_order ?? 0
      }
    })
  }
}

function openRegionConfig(row) {
  regionConfigPondId.value = row.id
  regionConfigPondName.value = row.name || ''
  regionConfigVisible.value = true
  regionListLoading.value = true
  seatCodeMap.value = {}
  Promise.all([fetchRegions(row.id), fetchSeats(row.id)]).finally(() => {
    regionListLoading.value = false
  })
}

function closeRegionConfig() {
  regionConfigVisible.value = false
  regionConfigPondId.value = null
  regionConfigPondName.value = ''
  regionList.value = []
  seatCodeMap.value = {}
}

async function fetchRegions(pondId) {
  if (!pondId) return
  try {
    const res = await getPondRegions(pondId)
    const data = res?.data ?? res
    regionList.value = data?.list ?? []
  } catch (_) {
    regionList.value = []
  }
}

async function fetchSeats(pondId) {
  if (!pondId) return
  try {
    const res = await getPondSeats(pondId)
    const data = res?.data ?? res
    const list = data?.list ?? []
    const map = {}
    for (const s of list) {
      const no = Number(s.seat_no)
      if (!Number.isNaN(no)) map[no] = s.code || ''
    }
    seatCodeMap.value = map
  } catch (_) {
    seatCodeMap.value = {}
  }
}

async function onSyncSeats() {
  if (!regionConfigPondId.value) return
  seatSyncLoading.value = true
  try {
    const res = await syncPondSeats(regionConfigPondId.value)
    const d = res?.data ?? res
    const data = d?.data ?? d
    ElMessage.success(`已同步座位：总${data?.total ?? 0}，新增${data?.created ?? 0}，更新${data?.updated ?? 0}`)
    await fetchSeats(regionConfigPondId.value)
  } catch (_) {
    // error already shown by request
  } finally {
    seatSyncLoading.value = false
  }
}

async function onDownloadSeatZip() {
  if (!regionConfigPondId.value) return
  seatZipLoading.value = true
  try {
    const res = await downloadPondSeatQrsZip(regionConfigPondId.value)
    const d = res?.data ?? res
    const data = d?.data ?? d
    const url = data?.zip_url
    if (url) {
      window.location.href = url
      ElMessage.success(`已生成ZIP（${data?.files ?? 0}张）`)
    } else {
      ElMessage.warning('生成ZIP失败：未返回下载地址')
    }
  } catch (_) {
  } finally {
    seatZipLoading.value = false
  }
}

async function onCleanupSeatQrs() {
  if (!regionConfigPondId.value) return
  const ok = await ElMessageBox.confirm('将删除该池塘已生成的二维码图片与ZIP包，是否继续？', '清理确认', {
    type: 'warning',
    confirmButtonText: '确定清理',
    cancelButtonText: '取消',
  }).then(() => true).catch(() => false)
  if (!ok) return

  seatCleanupLoading.value = true
  try {
    await cleanupPondSeatQrs(regionConfigPondId.value)
    ElMessage.success('清理完成')
  } catch (_) {
  } finally {
    seatCleanupLoading.value = false
  }
}

function openRegionForm() {
  regionForm.name = ''
  regionForm.start_no = regionList.value.length ? Math.max(...regionList.value.map((r) => r.end_no), 0) + 1 : 1
  regionForm.end_no = regionForm.start_no
  regionDialogVisible.value = true
}

function resetRegionForm() {
  regionFormRef.value?.resetFields?.()
}

async function submitRegion() {
  await regionFormRef.value?.validate().catch(() => {})
  if (regionForm.end_no < regionForm.start_no) {
    ElMessage.warning('结束序号不能小于起始序号')
    return
  }
  regionSubmitLoading.value = true
  try {
    await createPondRegion({
      pond_id: regionConfigPondId.value,
      name: regionForm.name,
      start_no: regionForm.start_no,
      end_no: regionForm.end_no,
    })
    ElMessage.success('添加成功')
    regionDialogVisible.value = false
    fetchRegions(regionConfigPondId.value)
  } catch (_) {}
  finally {
    regionSubmitLoading.value = false
  }
}

function onDeleteRegion(row) {
  ElMessageBox.confirm(`确定删除区域「${row.name}」？`, '提示', { type: 'warning' })
    .then(async () => {
      await deletePondRegion(row.id)
      ElMessage.success('已删除')
      fetchRegions(regionConfigPondId.value)
    })
    .catch(() => {})
}

function openFeeConfig(row) {
  feeConfigPondId.value = row.id
  feeConfigPondName.value = row.name || ''
  feeConfigVisible.value = true
  feeListLoading.value = true
  fetchFeeRules(row.id).finally(() => { feeListLoading.value = false })
}

function closeFeeConfig() {
  feeConfigVisible.value = false
  feeConfigPondId.value = null
  feeConfigPondName.value = ''
  feeList.value = []
}

async function fetchFeeRules(pondId) {
  if (!pondId) return
  try {
    const res = await getPondFeeRules(pondId)
    const data = res?.data ?? res
    feeList.value = data?.list ?? []
  } catch (_) {
    feeList.value = []
  }
}

function openFeeForm(row) {
  if (row?.id) {
    feeEditId.value = row.id
    feeForm.name = row.name ?? ''
    feeForm.duration_value = Number(row.duration_value) ?? 0
    feeForm.duration_unit = row.duration_unit ?? 'hour'
    feeForm.amount = Number(row.amount) ?? 0
    feeForm.deposit = Number(row.deposit) ?? 0
    feeForm.sort_order = Number(row.sort_order) ?? 0
  } else {
    feeEditId.value = null
    feeForm.name = ''
    feeForm.duration_value = 0
    feeForm.duration_unit = 'hour'
    feeForm.amount = 0
    feeForm.deposit = 0
    feeForm.sort_order = feeList.value.length ? Math.max(...feeList.value.map((r) => Number(r.sort_order) || 0), 0) + 1 : 0
  }
  feeDialogVisible.value = true
}

function resetFeeForm() {
  feeFormRef.value?.resetFields?.()
}

async function submitFee() {
  const passed = await feeFormRef.value?.validate().catch(() => false)
  if (!passed) return
  feeSubmitLoading.value = true
  try {
    if (feeEditId.value) {
      await updatePondFeeRule(feeEditId.value, {
        name: feeForm.name,
        duration_value: feeForm.duration_value,
        duration_unit: feeForm.duration_unit,
        amount: feeForm.amount,
        deposit: feeForm.deposit,
        sort_order: feeForm.sort_order,
      })
      ElMessage.success('更新成功')
    } else {
      await createPondFeeRule({
        pond_id: feeConfigPondId.value,
        name: feeForm.name,
        duration_value: feeForm.duration_value,
        duration_unit: feeForm.duration_unit,
        amount: feeForm.amount,
        deposit: feeForm.deposit,
        sort_order: feeForm.sort_order,
      })
      ElMessage.success('添加成功')
    }
    feeDialogVisible.value = false
    fetchFeeRules(feeConfigPondId.value)
  } catch (_) {}
  finally {
    feeSubmitLoading.value = false
  }
}

function onDeleteFeeRule(row) {
  ElMessageBox.confirm(`确定删除收费规则「${row.name}」？`, '提示', { type: 'warning' })
    .then(async () => {
      await deletePondFeeRule(row.id)
      ElMessage.success('已删除')
      fetchFeeRules(feeConfigPondId.value)
    })
    .catch(() => {})
}

function openReturnConfig(row) {
  returnConfigPondId.value = row.id
  returnConfigPondName.value = row.name || ''
  returnConfigVisible.value = true
  returnNewRows.value = []
  returnListLoading.value = true
  fetchReturnRules(row.id).finally(() => { returnListLoading.value = false })
}

function closeReturnConfig() {
  returnConfigVisible.value = false
  returnConfigPondId.value = null
  returnConfigPondName.value = ''
  returnList.value = []
  returnNewRows.value = []
}

async function fetchReturnRules(pondId) {
  if (!pondId) return
  try {
    const res = await getPondReturnRules(pondId)
    const data = res?.data ?? res
    const raw = data?.list ?? []
    returnList.value = raw.map((r) => ({
      ...r,
      lower_limit: Number(r.lower_limit) ?? 0,
      upper_limit: Number(r.upper_limit) ?? 0,
      amount: Number(r.amount) ?? 0,
      sort_order: Number(r.sort_order) ?? 0,
    }))
  } catch (_) {
    returnList.value = []
  }
}

function addReturnRow() {
  returnNewRows.value.push({
    _new: true,
    _tid: 'new_' + Date.now(),
    name: '',
    lower_limit: 0,
    upper_limit: 0,
    return_type: 'jin',
    amount: 0,
    sort_order: returnDisplayList.value.length,
  })
}

async function saveReturnRow(row) {
  if (!(row.name && String(row.name).trim())) {
    ElMessage.warning('请输入规则名称')
    return
  }
  const key = getReturnRowKey(row)
  savingReturnKey.value = key
  try {
    if (row._new) {
      await createPondReturnRule({
        pond_id: returnConfigPondId.value,
        name: String(row.name).trim(),
        lower_limit: Number(row.lower_limit) || 0,
        upper_limit: Number(row.upper_limit) || 0,
        return_type: row.return_type || 'jin',
        amount: Number(row.amount) || 0,
        sort_order: Number(row.sort_order) || 0,
      })
      ElMessage.success('添加成功')
      returnNewRows.value = returnNewRows.value.filter((r) => r !== row)
      await fetchReturnRules(returnConfigPondId.value)
    } else {
      await updatePondReturnRule(row.id, {
        name: String(row.name).trim(),
        lower_limit: Number(row.lower_limit) || 0,
        upper_limit: Number(row.upper_limit) || 0,
        return_type: row.return_type || 'jin',
        amount: Number(row.amount) || 0,
        sort_order: Number(row.sort_order) || 0,
      })
      ElMessage.success('保存成功')
      await fetchReturnRules(returnConfigPondId.value)
    }
  } catch (_) {}
  finally {
    savingReturnKey.value = null
  }
}

function deleteReturnRow(row) {
  if (row._new) {
    returnNewRows.value = returnNewRows.value.filter((r) => r !== row)
    return
  }
  ElMessageBox.confirm(`确定删除回鱼规则「${row.name}」？`, '提示', { type: 'warning' })
    .then(async () => {
      await deletePondReturnRule(row.id)
      ElMessage.success('已删除')
      fetchReturnRules(returnConfigPondId.value)
    })
    .catch(() => {})
}

function resetForm() {
  formRef.value?.resetFields?.()
}

async function submitEdit() {
  await formRef.value?.validate().catch(() => {})
  submitLoading.value = true
  try {
    const payload = { ...editForm }
    if (payload.open_time === '') payload.open_time = null
    if (editId.value) {
      await updatePond(editId.value, payload)
      ElMessage.success('更新成功')
    } else {
      await createPond(payload)
      ElMessage.success('添加成功')
    }
    dialogVisible.value = false
    fetchList()
  } catch (_) {
    // error already shown by request
  } finally {
    submitLoading.value = false
  }
}

function onDelete(row) {
  ElMessageBox.confirm('确定删除该池塘？删除后其钓位区域、收费规则、回鱼规则也会一并删除。', '提示', { type: 'warning' })
    .then(async () => {
      await deletePond(row.id)
      ElMessage.success('已删除')
      fetchList()
    })
    .catch(() => {})
}

onMounted(() => {
  loadVenueOptions()
  fetchList()
})
</script>

<style scoped>
.card-header { display: flex; justify-content: space-between; align-items: center; }
.filter-form { margin-bottom: 12px; }
.upload-wrap { display: flex; flex-direction: column; gap: 8px; }
.upload-preview { width: 160px; height: 90px; border-radius: 6px; border: 1px solid var(--el-border-color); cursor: pointer; }
.region-config-body { min-height: 120px; }
.region-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px; }
.region-toolbar-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
.region-tip { font-size: 12px; color: var(--el-text-color-secondary); }
.region-table { margin-top: 0; }
.duration-with-unit { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.fee-form-wrap { min-height: 200px; }
.return-config-wrap { min-height: 120px; }
.return-inline-table { margin-top: 0; }
.seat-numbers-section { margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--el-border-color-lighter); }
.seat-numbers-label { font-size: 13px; color: var(--el-text-color-regular); margin-bottom: 10px; }
.seat-region { margin-bottom: 14px; }
.seat-region:last-child { margin-bottom: 0; }
.seat-region-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
.seat-region-name { font-weight: 600; color: var(--el-text-color-primary); font-size: 13px; }
.seat-region-meta { font-size: 12px; color: var(--el-text-color-secondary); white-space: nowrap; }
.seat-numbers-wrap { display: flex; flex-wrap: wrap; gap: 6px; max-height: 200px; overflow-y: auto; }
.seat-num { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-width: 44px; height: 32px; padding: 0 8px; font-size: 12px; border-radius: 6px; background: var(--el-fill-color-light); color: var(--el-text-color-primary); }
.seat-icon { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; min-width: 20px; min-height: 20px; }
.seat-icon :deep(.seat-svg),
.seat-icon :deep(svg) { display: inline-block !important; width: 20px !important; height: 20px !important; flex-shrink: 0; fill: #606266; }
.seat-no { font-weight: 600; }
</style>
