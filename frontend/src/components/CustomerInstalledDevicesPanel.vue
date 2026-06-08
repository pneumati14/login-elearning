<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import {
  useCustomersStore,
  emptyInstalledDeviceFields,
  type Customer,
  type InstalledDevice,
  type InstalledDeviceFields,
} from '@/stores/customers'
import { useProductsStore, productStatus } from '@/stores/products'
import AppSelect from '@/components/AppSelect.vue'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const props = defineProps<{ customer: Customer }>()

const { t } = useI18n()
const store = useCustomersStore()
const productsStore = useProductsStore()
const { products } = storeToRefs(productsStore)

onMounted(() => {
  if (0 === products.value.length) productsStore.fetchProducts()
})

// A single collapsible form serves both creating and editing, mirroring
// the other customer-detail tabs. editingId === null means creating.
const showForm = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<InstalledDeviceFields>(emptyInstalledDeviceFields())
const saving = ref(false)
const formError = ref<string | null>(null)

// Optional catalogue picker: a null entry keeps the name free-text.
const productSelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: t('adminCustomers.deviceNoProduct') },
  ...products.value
    .filter((p) => 'active' === productStatus(p))
    .sort((a, b) => a.name.localeCompare(b.name, 'hu'))
    .map((p) => ({ value: p.id, label: p.name })),
])

// Picking a product prefills the device name; it stays freely editable.
function onProductPicked(): void {
  if (null === form.productId) return
  const product = products.value.find((p) => p.id === form.productId)
  if (product) form.name = product.name
}

function openNew(): void {
  editingId.value = null
  Object.assign(form, emptyInstalledDeviceFields())
  formError.value = null
  showForm.value = true
}

function openEdit(d: InstalledDevice): void {
  editingId.value = d.id
  form.productId = d.productId
  form.name = d.name
  form.description = d.description
  form.quantity = d.quantity
  form.installedAt = d.installedAt
  form.location = d.location
  formError.value = null
  showForm.value = true
}

function closeForm(): void {
  showForm.value = false
  editingId.value = null
}

async function onSubmit(): Promise<void> {
  if ('' === form.name.trim()) {
    formError.value = t('adminCustomers.deviceNameRequired')
    return
  }
  if (form.quantity < 1) {
    formError.value = t('adminCustomers.deviceQuantityRequired')
    return
  }
  saving.value = true
  const result =
    null === editingId.value
      ? await store.createInstalledDevice(props.customer.id, { ...form })
      : await store.updateInstalledDevice(props.customer.id, editingId.value, { ...form })
  saving.value = false

  if (result.ok) {
    closeForm()
  } else {
    formError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDelete(d: InstalledDevice): Promise<void> {
  if (!window.confirm(t('adminCustomers.deviceConfirmDelete', { name: d.name }))) return
  const result = await store.deleteInstalledDevice(props.customer.id, d.id)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.deleteFailed'))
  } else if (editingId.value === d.id) {
    closeForm()
  }
}

const devices = computed(() => props.customer.installedDevices)
const totalQuantity = computed(() => devices.value.reduce((sum, d) => sum + d.quantity, 0))
</script>

<template>
  <div class="devices-panel">
    <div v-if="!showForm" class="devices-head">
      <button type="button" class="btn-new" @click="openNew()">
        {{ '+ ' + t('adminCustomers.deviceAdd') }}
      </button>
    </div>

    <!-- ── Shared create / edit form ────────────────────────────────── -->
    <form v-if="showForm" class="device-form" @submit.prevent="onSubmit">
      <h4>{{ null === editingId ? t('adminCustomers.deviceAdd') : t('adminCustomers.deviceEdit') }}</h4>
      <div class="device-form-grid">
        <label class="field field--wide">
          <span>{{ t('adminCustomers.deviceProduct') }}</span>
          <AppSelect
            v-model="form.productId"
            :options="productSelectOptions"
            :placeholder="t('adminCustomers.deviceNoProduct')"
            @change="onProductPicked"
          />
        </label>
        <label class="field field--wide">
          <span>{{ t('adminCustomers.deviceName') }} *</span>
          <input v-model="form.name" type="text" maxlength="255" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.deviceQuantity') }} *</span>
          <input v-model.number="form.quantity" type="number" min="1" step="1" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.deviceInstalledAt') }}</span>
          <input v-model="form.installedAt" type="date" />
        </label>
        <label class="field field--wide">
          <span>{{ t('adminCustomers.deviceDescription') }}</span>
          <textarea v-model="form.description" rows="2" />
        </label>
        <label class="field field--wide">
          <span>{{ t('adminCustomers.deviceLocation') }}</span>
          <textarea v-model="form.location" rows="2" />
        </label>
      </div>

      <p v-if="formError" class="msg msg--error">{{ formError }}</p>

      <div class="form-actions">
        <button type="submit" class="btn-submit" :disabled="saving">
          {{ saving ? t('admin.saving') : null === editingId ? t('adminCustomers.deviceAddButton') : t('admin.save') }}
        </button>
        <button type="button" class="btn-ghost" @click="closeForm">
          {{ t('adminUsers.cancel') }}
        </button>
      </div>
    </form>

    <!-- ── Devices table (hidden while the form is open) ────────────── -->
    <p v-if="!showForm && devices.length === 0" class="state">{{ t('adminCustomers.devicesEmpty') }}</p>

    <div v-else-if="!showForm" class="device-table-wrap">
      <table class="device-table">
        <thead>
          <tr>
            <th>{{ t('adminCustomers.deviceName') }}</th>
            <th>{{ t('adminCustomers.deviceDescription') }}</th>
            <th class="col-num">{{ t('adminCustomers.deviceQuantity') }}</th>
            <th>{{ t('adminCustomers.deviceInstalledAt') }}</th>
            <th>{{ t('adminCustomers.deviceLocation') }}</th>
            <th class="col-actions"><span class="sr-only">{{ t('adminCustomers.colActions') }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in devices" :key="d.id" class="device-tr" :class="{ 'is-editing': editingId === d.id }">
            <td class="cell-name">{{ d.name }}</td>
            <td class="cell-desc">{{ d.description || '—' }}</td>
            <td class="col-num">{{ d.quantity }}</td>
            <td>{{ d.installedAt || '—' }}</td>
            <td class="cell-loc">{{ d.location || '—' }}</td>
            <td class="col-actions">
              <div class="device-row-actions">
                <button
                  type="button"
                  class="btn-icon"
                  :title="t('admin.edit')"
                  :aria-label="t('admin.edit')"
                  @click="openEdit(d)"
                >
                  <IconEdit />
                </button>
                <button
                  type="button"
                  class="btn-icon btn-icon--danger"
                  :title="t('admin.delete')"
                  :aria-label="t('admin.delete')"
                  @click="onDelete(d)"
                >
                  <IconDelete />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td>{{ t('adminCustomers.deviceTotal') }}</td>
            <td></td>
            <td class="col-num">{{ totalQuantity }}</td>
            <td colspan="3"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<style scoped>
.devices-head {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 1.1rem;
}

.btn-new {
  white-space: nowrap;
  padding: 0.5rem 1.1rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.55rem;
  color: #fff;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
}

/* ── Shared form ──────────────────────────────────────────────────── */
.device-form {
  margin-bottom: 1.6rem;
  padding: 1.1rem 1.2rem 1.3rem;
  background: #fff;
  border: 1px dashed #d4dae6;
  border-radius: 0.7rem;
}

.device-form h4 {
  margin: 0 0 0.9rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-weight: 700;
}

.device-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.8rem 1rem;
  margin-bottom: 1rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 0;
}

.field--wide {
  grid-column: 1 / -1;
}

.field span {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
}

.field input,
.field textarea {
  padding: 0.55rem 0.7rem;
  background: #f7f8fb;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.field input:focus,
.field textarea:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
  background: #fff;
}

.form-actions {
  display: flex;
  gap: 0.6rem;
}

.btn-submit {
  padding: 0.6rem 1.3rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.5rem;
  color: #fff;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: default;
}

.btn-ghost {
  padding: 0.6rem 1.3rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-ghost:hover {
  border-color: var(--login-secondary, #0c1c40);
}

.msg {
  margin: 0 0 0.8rem;
  font-size: 0.88rem;
  font-weight: 600;
}

.msg--error {
  color: #b3122e;
}

/* ── Table ────────────────────────────────────────────────────────── */
.device-table-wrap {
  overflow-x: auto;
}

.device-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.device-table thead th {
  padding: 0.6rem 0.85rem;
  text-align: left;
  color: #8b94a6;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
  border-bottom: 2px solid #e3e7ee;
}

.device-tr > td {
  padding: 0.7rem 0.85rem;
  color: #545f71;
  vertical-align: middle;
  border-bottom: 1px solid #eef1f6;
}

.device-tr:hover > td {
  background: #f7f8fb;
}

.device-tr.is-editing > td {
  background: #fef6f7;
}

.cell-name {
  color: var(--login-secondary, #0c1c40);
  font-weight: 700;
}

.cell-desc,
.cell-loc {
  word-break: break-word;
}

.col-num {
  text-align: right;
  white-space: nowrap;
}

.device-table tfoot td {
  padding: 0.65rem 0.85rem;
  color: var(--login-secondary, #0c1c40);
  font-weight: 700;
  border-top: 2px solid #e3e7ee;
}

.col-actions {
  text-align: right;
  white-space: nowrap;
}

.device-row-actions {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
  flex-shrink: 0;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: #545f71;
  cursor: pointer;
  transition:
    color 0.15s,
    border-color 0.15s,
    background 0.15s;
}

.btn-icon:hover {
  border-color: var(--login-secondary, #0c1c40);
  color: var(--login-secondary, #0c1c40);
}

.btn-icon--danger:hover {
  background: var(--login-primary, #ed2044);
  border-color: var(--login-primary, #ed2044);
  color: #fff;
}

.btn-icon:focus-visible {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: 1px;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.state {
  margin: 0;
  padding: 1.3rem;
  background: #f7f8fb;
  border-radius: 0.7rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1rem;
}

@media (max-width: 767.98px) {
  .device-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
