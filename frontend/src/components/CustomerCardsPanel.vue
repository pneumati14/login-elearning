<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import {
  useCustomersStore,
  CARD_ORDER_STATUSES,
  type Customer,
  type CustomerCard,
  type CustomerCardFields,
  type CardOrder,
  type CardOrderFields,
  type CardOrderStatus,
} from '@/stores/customers'
import { useProductsStore, productStatus } from '@/stores/products'
import { useSuppliersStore } from '@/stores/suppliers'
import AppSelect from '@/components/AppSelect.vue'
import IconEdit from '@/components/icons/IconEdit.vue'
import IconDelete from '@/components/icons/IconDelete.vue'

const props = defineProps<{ customer: Customer }>()

const { t, locale } = useI18n()
const store = useCustomersStore()
const productsStore = useProductsStore()
const suppliersStore = useSuppliersStore()
const { products } = storeToRefs(productsStore)
const { suppliers } = storeToRefs(suppliersStore)

onMounted(() => {
  if (0 === products.value.length) productsStore.fetchProducts()
  if (0 === suppliers.value.length) suppliersStore.fetchSuppliers()
})

function todayISO(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function fmtDate(iso: string): string {
  return new Date(`${iso}T00:00:00`).toLocaleDateString(locale.value)
}

function fmtMoney(amount: number, currency: string): string {
  return new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(amount)
}

// ── Order economics (totals + margin) ────────────────────────────────
function saleTotal(o: CardOrder): number | null {
  return null === o.unitSalePrice ? null : Number(o.unitSalePrice) * o.quantity
}

/** Margin = (sale − purchase) × qty; pct relative to the sale total. */
function margin(o: CardOrder): { amount: number; pct: number | null } | null {
  if (null === o.unitSalePrice || null === o.unitPurchasePrice) return null
  const amount = (Number(o.unitSalePrice) - Number(o.unitPurchasePrice)) * o.quantity
  const sale = Number(o.unitSalePrice) * o.quantity
  return { amount, pct: sale > 0 ? Math.round((100 * amount) / sale) : null }
}

/** Per-currency revenue + margin sums of one card's priced orders. */
function cardTotals(card: CustomerCard): { currency: string; sale: number; margin: number | null }[] {
  const map: Record<string, { sale: number; margin: number | null }> = {}
  for (const o of card.orders) {
    const sale = saleTotal(o)
    if (null === sale) continue
    map[o.currency] ??= { sale: 0, margin: 0 }
    map[o.currency]!.sale += sale
    const m = margin(o)
    // One unpriced purchase makes the currency's margin unknowable.
    if (null === m) {
      map[o.currency]!.margin = null
    } else if (null !== map[o.currency]!.margin) {
      map[o.currency]!.margin! += m.amount
    }
  }
  return Object.entries(map)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([currency, sums]) => ({ currency, ...sums }))
}

// Active suppliers for the picker; a card's already-linked supplier stays
// selectable even if it has been deactivated since.
const supplierOptions = computed(() => {
  const linkedId = editingCardId.value
    ? props.customer.cards.find((c) => c.id === editingCardId.value)?.supplierId
    : null
  return suppliers.value
    .filter((s) => s.isActive || s.id === linkedId)
    .sort((a, b) => a.name.localeCompare(b.name, 'hu'))
})

const productOptions = computed(() =>
  products.value.filter((p) => 'active' === productStatus(p)).sort((a, b) => a.name.localeCompare(b.name, 'hu')),
)

// ── AppSelect option lists ───────────────────────────────────────────
const cardSupplierSelectOptions = computed<{ value: number | null; label: string }[]>(() => [
  { value: null, label: '—' },
  ...supplierOptions.value.map((s) => ({ value: s.id, label: s.name })),
])
// No null entry: like the old disabled placeholder, a product can't be
// "unpicked" — the empty state only shows via the placeholder prop.
const orderProductSelectOptions = computed<{ value: number | null; label: string }[]>(() =>
  productOptions.value.map((p) => ({ value: p.id, label: p.name })),
)
const orderStatusSelectOptions = computed<{ value: CardOrderStatus; label: string }[]>(() =>
  CARD_ORDER_STATUSES.map((s) => ({ value: s, label: t('adminCustomers.orderStatus_' + s) })),
)
const currencySelectOptions = computed<{ value: string; label: string }[]>(() =>
  ORDER_CURRENCIES.map((c) => ({ value: c, label: c })),
)

// ── Card create/edit form ────────────────────────────────────────────
const emptyCardFields = (): CustomerCardFields => ({ type: '', uniqueness: null, supplierId: null })

const showCardForm = ref(false)
const editingCardId = ref<number | null>(null)
const cardForm = reactive<CustomerCardFields>(emptyCardFields())
const savingCard = ref(false)
const cardError = ref<string | null>(null)

function openCreateCard(): void {
  Object.assign(cardForm, emptyCardFields())
  editingCardId.value = null
  cardError.value = null
  showCardForm.value = true
}

function openEditCard(card: CustomerCard): void {
  Object.assign(cardForm, { type: card.type, uniqueness: card.uniqueness, supplierId: card.supplierId })
  editingCardId.value = card.id
  cardError.value = null
  showCardForm.value = true
}

function closeCardForm(): void {
  showCardForm.value = false
  editingCardId.value = null
}

async function onSubmitCard(): Promise<void> {
  cardError.value = null
  savingCard.value = true
  const result =
    null === editingCardId.value
      ? await store.createCard(props.customer.id, { ...cardForm })
      : await store.updateCard(props.customer.id, editingCardId.value, { ...cardForm })
  savingCard.value = false
  if (result.ok) {
    closeCardForm()
  } else {
    cardError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDeleteCard(card: CustomerCard): Promise<void> {
  if (!window.confirm(t('adminCustomers.confirmDeleteCard', { name: card.type }))) return
  const result = await store.deleteCard(props.customer.id, card.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}

// ── Order form (per card) ────────────────────────────────────────────
const ORDER_CURRENCIES = ['HUF', 'EUR', 'USD']

const emptyOrderFields = (): CardOrderFields => ({
  productId: null,
  quantity: null,
  unitPurchasePrice: null,
  unitSalePrice: null,
  currency: 'HUF',
  orderedAt: todayISO(),
  status: 'quote',
})

// Picking a product prefills the sale price + currency from the catalogue.
function onOrderProductPicked(): void {
  if (null === orderForm.productId) return
  const product = products.value.find((p) => p.id === orderForm.productId)
  if (!product) return
  if (null !== product.unitPrice) orderForm.unitSalePrice = product.unitPrice
  orderForm.currency = product.currency
}

// Live totals preview while typing in the order form.
const orderFormPreview = computed(() => {
  const qty = Number(orderForm.quantity ?? 0)
  const sale = Number(orderForm.unitSalePrice ?? 0)
  const buy = Number(orderForm.unitPurchasePrice ?? 0)
  if (qty <= 0 || sale <= 0) return null
  const total = sale * qty
  const marginAmount = buy > 0 ? (sale - buy) * qty : null
  return {
    total: fmtMoney(total, orderForm.currency),
    margin:
      null === marginAmount
        ? null
        : `${fmtMoney(marginAmount, orderForm.currency)} (${Math.round((100 * marginAmount) / total)}%)`,
  }
})

const orderCardId = ref<number | null>(null)
const editingOrderId = ref<number | null>(null)
const orderForm = reactive<CardOrderFields>(emptyOrderFields())
const savingOrder = ref(false)
const orderError = ref<string | null>(null)

function openCreateOrder(card: CustomerCard): void {
  Object.assign(orderForm, emptyOrderFields())
  orderCardId.value = card.id
  editingOrderId.value = null
  orderError.value = null
}

function openEditOrder(card: CustomerCard, order: CardOrder): void {
  Object.assign(orderForm, {
    productId: order.productId,
    quantity: order.quantity,
    unitPurchasePrice: order.unitPurchasePrice,
    unitSalePrice: order.unitSalePrice,
    currency: order.currency,
    orderedAt: order.orderedAt,
    status: order.status,
  })
  orderCardId.value = card.id
  editingOrderId.value = order.id
  orderError.value = null
}

function closeOrderForm(): void {
  orderCardId.value = null
  editingOrderId.value = null
}

async function onSubmitOrder(card: CustomerCard): Promise<void> {
  orderError.value = null
  // The native select's `required` is gone with AppSelect — guard here.
  if (null === orderForm.productId) {
    orderError.value = t('adminCustomers.orderPickProduct')
    return
  }
  savingOrder.value = true
  const result =
    null === editingOrderId.value
      ? await store.createCardOrder(props.customer.id, card.id, { ...orderForm })
      : await store.updateCardOrder(props.customer.id, card.id, editingOrderId.value, { ...orderForm })
  savingOrder.value = false
  if (result.ok) {
    closeOrderForm()
  } else {
    orderError.value = result.error ?? t('admin.saveFailed')
  }
}

async function onDeleteOrder(card: CustomerCard, order: CardOrder): Promise<void> {
  if (!window.confirm(t('adminCustomers.confirmDeleteOrder', { name: order.productName }))) return
  const result = await store.deleteCardOrder(props.customer.id, card.id, order.id)
  if (!result.ok) window.alert(result.error ?? t('admin.deleteFailed'))
}

async function onStatusChange(card: CustomerCard, order: CardOrder, status: CardOrderStatus): Promise<void> {
  if (status === order.status) return
  const result = await store.moveCardOrderStatus(props.customer.id, card.id, order.id, status)
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}

// ── Kanban: all orders across cards, by workflow status ─────────────
interface KanbanItem {
  cardId: number
  cardType: string
  order: CardOrder
}

const kanbanItems = computed<KanbanItem[]>(() =>
  props.customer.cards.flatMap((card) =>
    card.orders.map((order) => ({ cardId: card.id, cardType: card.type, order })),
  ),
)

function itemsFor(status: CardOrderStatus): KanbanItem[] {
  return kanbanItems.value
    .filter((item) => item.order.status === status)
    .sort((a, b) => (a.order.orderedAt > b.order.orderedAt ? -1 : 1))
}

const dragItem = ref<KanbanItem | null>(null)
const dragOverStatus = ref<CardOrderStatus | null>(null)

function onItemDragStart(item: KanbanItem, e: DragEvent): void {
  dragItem.value = item
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    // Firefox requires some data to be set for the drag to start.
    e.dataTransfer.setData('text/plain', String(item.order.id))
  }
}

function onColumnDragOver(status: CardOrderStatus, e: DragEvent): void {
  e.preventDefault()
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move'
  dragOverStatus.value = status
}

function onItemDragEnd(): void {
  dragItem.value = null
  dragOverStatus.value = null
}

async function onColumnDrop(status: CardOrderStatus): Promise<void> {
  const item = dragItem.value
  onItemDragEnd()
  if (null === item || item.order.status === status) return
  const result = await store.moveCardOrderStatus(props.customer.id, item.cardId, item.order.id, status)
  if (!result.ok) window.alert(result.error ?? t('admin.saveFailed'))
}
</script>

<template>
  <div class="card-panel">
    <div class="card-head">
      <h2>{{ t('adminCustomers.cardsHeader') }}</h2>
      <button type="button" class="btn-submit" @click="showCardForm ? closeCardForm() : openCreateCard()">
        {{ showCardForm ? t('adminUsers.cancel') : '+ ' + t('adminCustomers.cardAddButton') }}
      </button>
    </div>

    <!-- ── Card create / edit form ─────────────────────────────────── -->
    <form v-if="showCardForm" class="card-form" @submit.prevent="onSubmitCard">
      <h3>{{ null === editingCardId ? t('adminCustomers.cardAddButton') : t('admin.edit') }}</h3>
      <div class="card-form-grid">
        <label class="field">
          <span>{{ t('adminCustomers.cardType') }} *</span>
          <input v-model="cardForm.type" type="text" required maxlength="255" :placeholder="t('adminCustomers.cardTypePlaceholder')" />
        </label>
        <label class="field">
          <span>{{ t('adminCustomers.cardSupplier') }}</span>
          <AppSelect v-model="cardForm.supplierId" :options="cardSupplierSelectOptions" />
        </label>
        <label class="field field--wide">
          <span>{{ t('adminCustomers.cardUniqueness') }}</span>
          <textarea v-model="cardForm.uniqueness" rows="2" :placeholder="t('adminCustomers.cardUniquenessPlaceholder')" />
        </label>
      </div>
      <p v-if="cardError" class="msg msg--error">{{ cardError }}</p>
      <div class="form-actions">
        <button type="submit" class="btn-submit" :disabled="savingCard">
          {{ savingCard ? t('admin.saving') : t('admin.save') }}
        </button>
        <button type="button" class="btn-ghost" @click="closeCardForm">{{ t('adminUsers.cancel') }}</button>
      </div>
    </form>

    <!-- ── Order kanban: drag between workflow statuses ────────────── -->
    <div v-if="kanbanItems.length > 0" class="kanban-wrap">
      <h3 class="kanban-title">{{ t('adminCustomers.orderKanbanHeader') }}</h3>
      <p class="kanban-hint">{{ t('adminCustomers.orderKanbanHint') }}</p>
      <div class="kanban">
        <div
          v-for="s in CARD_ORDER_STATUSES"
          :key="s"
          class="kanban-col"
          :class="{ 'is-over': dragOverStatus === s }"
          @dragover="onColumnDragOver(s, $event)"
          @drop="onColumnDrop(s)"
        >
          <div class="kanban-col-head" :class="`kanban-col-head--${s}`">
            <span>{{ t('adminCustomers.orderStatus_' + s) }}</span>
            <span class="kanban-count">{{ itemsFor(s).length }}</span>
          </div>
          <div class="kanban-col-body">
            <div
              v-for="item in itemsFor(s)"
              :key="item.order.id"
              class="kanban-item"
              :class="{ 'is-dragging': dragItem?.order.id === item.order.id }"
              draggable="true"
              @dragstart="onItemDragStart(item, $event)"
              @dragend="onItemDragEnd"
            >
              <span class="kanban-item-product">{{ item.order.productName }}</span>
              <span class="kanban-item-meta">
                {{ item.order.quantity }} {{ t('adminCustomers.orderPcs') }} · {{ item.cardType }}
              </span>
              <span v-if="null !== saleTotal(item.order)" class="kanban-item-total">
                {{ fmtMoney(saleTotal(item.order)!, item.order.currency) }}
              </span>
              <span class="kanban-item-date">{{ fmtDate(item.order.orderedAt) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <p v-if="customer.cards.length === 0" class="muted">{{ t('adminCustomers.cardsEmpty') }}</p>

    <!-- ── Cards ───────────────────────────────────────────────────── -->
    <div v-for="card in customer.cards" :key="card.id" class="card-item">
      <div class="card-item-head">
        <div class="card-item-title">
          <span class="card-type">💳 {{ card.type }}</span>
          <span v-if="card.supplierName" class="card-supplier">{{ t('adminCustomers.cardSupplier') }}: {{ card.supplierName }}</span>
        </div>
        <div class="card-item-actions">
          <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="openEditCard(card)">
            <IconEdit />
          </button>
          <button
            type="button"
            class="btn-icon btn-icon--danger"
            :title="t('admin.delete')"
            :aria-label="t('admin.delete')"
            @click="onDeleteCard(card)"
          >
            <IconDelete />
          </button>
        </div>
      </div>

      <p v-if="card.uniqueness" class="card-uniqueness">{{ card.uniqueness }}</p>

      <!-- Orders -->
      <div class="orders-head">
        <h4>{{ t('adminCustomers.cardOrders') }}</h4>
        <button
          type="button"
          class="btn-mini"
          @click="orderCardId === card.id && null === editingOrderId ? closeOrderForm() : openCreateOrder(card)"
        >
          {{ orderCardId === card.id && null === editingOrderId ? t('adminUsers.cancel') : '+ ' + t('adminCustomers.orderAddButton') }}
        </button>
      </div>

      <form v-if="orderCardId === card.id" class="order-form" @submit.prevent="onSubmitOrder(card)">
        <label class="field">
          <span>{{ t('adminCustomers.orderProduct') }} *</span>
          <AppSelect
            v-model="orderForm.productId"
            :options="orderProductSelectOptions"
            :placeholder="t('adminCustomers.orderPickProduct')"
            @change="onOrderProductPicked"
          />
        </label>
        <label class="field field--narrow">
          <span>{{ t('adminCustomers.orderQuantity') }} *</span>
          <input v-model.number="orderForm.quantity" type="number" min="1" step="1" required />
        </label>
        <label class="field field--narrow">
          <span>{{ t('adminCustomers.orderPurchasePrice') }}</span>
          <input v-model="orderForm.unitPurchasePrice" type="number" min="0" step="any" />
        </label>
        <label class="field field--narrow">
          <span>{{ t('adminCustomers.orderSalePrice') }}</span>
          <input v-model="orderForm.unitSalePrice" type="number" min="0" step="any" />
        </label>
        <label class="field field--narrow">
          <span>{{ t('adminCustomers.currency') }}</span>
          <AppSelect v-model="orderForm.currency" :options="currencySelectOptions" />
        </label>
        <label class="field field--narrow">
          <span>{{ t('adminCustomers.orderDate') }} *</span>
          <input v-model="orderForm.orderedAt" type="date" required />
        </label>
        <label class="field field--narrow">
          <span>{{ t('adminCustomers.orderStatus') }}</span>
          <AppSelect v-model="orderForm.status" :options="orderStatusSelectOptions" />
        </label>
        <div class="order-form-actions">
          <button type="submit" class="btn-mini" :disabled="savingOrder">
            {{ savingOrder ? t('admin.saving') : t('admin.save') }}
          </button>
          <button type="button" class="btn-mini btn-mini--ghost" @click="closeOrderForm">{{ t('adminUsers.cancel') }}</button>
        </div>
        <p v-if="orderFormPreview" class="order-preview">
          {{ t('adminCustomers.orderTotal') }}: <strong>{{ orderFormPreview.total }}</strong>
          <template v-if="orderFormPreview.margin">
            · {{ t('adminCustomers.orderMargin') }}: <strong>{{ orderFormPreview.margin }}</strong>
          </template>
        </p>
        <p v-if="orderError" class="msg msg--error order-error">{{ orderError }}</p>
      </form>

      <p v-if="card.orders.length === 0" class="muted muted--small">{{ t('adminCustomers.ordersEmpty') }}</p>

      <table v-else class="order-table">
        <thead>
          <tr>
            <th>{{ t('adminCustomers.orderProduct') }}</th>
            <th class="num">{{ t('adminCustomers.orderQuantity') }}</th>
            <th class="num">{{ t('adminCustomers.orderPurchasePrice') }}</th>
            <th class="num">{{ t('adminCustomers.orderSalePrice') }}</th>
            <th class="num">{{ t('adminCustomers.orderTotal') }}</th>
            <th class="num">{{ t('adminCustomers.orderMargin') }}</th>
            <th>{{ t('adminCustomers.orderDate') }}</th>
            <th>{{ t('adminCustomers.orderStatus') }}</th>
            <th class="col-actions"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in card.orders" :key="o.id">
            <td class="order-product">{{ o.productName }}</td>
            <td class="num">{{ o.quantity }} {{ t('adminCustomers.orderPcs') }}</td>
            <td class="num">{{ null === o.unitPurchasePrice ? '—' : fmtMoney(Number(o.unitPurchasePrice), o.currency) }}</td>
            <td class="num">{{ null === o.unitSalePrice ? '—' : fmtMoney(Number(o.unitSalePrice), o.currency) }}</td>
            <td class="num order-total">{{ null === saleTotal(o) ? '—' : fmtMoney(saleTotal(o)!, o.currency) }}</td>
            <td class="num">
              <template v-if="margin(o)">
                <span :class="margin(o)!.amount >= 0 ? 'margin-pos' : 'margin-neg'">
                  {{ fmtMoney(margin(o)!.amount, o.currency) }}
                  <template v-if="null !== margin(o)!.pct"> ({{ margin(o)!.pct }}%)</template>
                </span>
              </template>
              <template v-else>—</template>
            </td>
            <td>{{ fmtDate(o.orderedAt) }}</td>
            <td>
              <AppSelect
                class="status-select"
                :class="`status-select--${o.status}`"
                compact
                :model-value="o.status"
                :options="orderStatusSelectOptions"
                :title="t('adminCustomers.orderToggleHint')"
                @change="(v) => onStatusChange(card, o, v)"
              />
            </td>
            <td class="col-actions">
              <div class="row-actions">
                <button type="button" class="btn-icon" :title="t('admin.edit')" :aria-label="t('admin.edit')" @click="openEditOrder(card, o)">
                  <IconEdit />
                </button>
                <button
                  type="button"
                  class="btn-icon btn-icon--danger"
                  :title="t('admin.delete')"
                  :aria-label="t('admin.delete')"
                  @click="onDeleteOrder(card, o)"
                >
                  <IconDelete />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
        <tfoot v-if="cardTotals(card).length > 0">
          <tr>
            <td colspan="4">{{ t('adminCustomers.feeTotalRow') }}</td>
            <td class="num order-total">
              <div v-for="tt in cardTotals(card)" :key="tt.currency">{{ fmtMoney(tt.sale, tt.currency) }}</div>
            </td>
            <td class="num">
              <div v-for="tt in cardTotals(card)" :key="tt.currency">
                <span v-if="null === tt.margin">—</span>
                <span v-else :class="tt.margin >= 0 ? 'margin-pos' : 'margin-neg'">
                  {{ fmtMoney(tt.margin, tt.currency) }}
                </span>
              </div>
            </td>
            <td colspan="3"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<style scoped>
.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.3rem;
}

.card-head h2 {
  margin: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.card-form {
  margin-bottom: 1.3rem;
  padding: 1.2rem 1.3rem;
  background: #f7f8fb;
  border-radius: 0.8rem;
}

.card-form h3 {
  margin: 0 0 1rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
  font-weight: 700;
}

.card-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.9rem 1rem;
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

.field--narrow {
  flex: 0 1 140px;
}

.field span {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
}

.field input,
.field textarea,
.field select {
  padding: 0.55rem 0.7rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
  font-family: inherit;
}

.field input:focus,
.field textarea:focus,
.field select:focus {
  outline: 2px solid var(--login-primary, #ed2044);
  outline-offset: -1px;
}

.form-actions {
  display: flex;
  gap: 0.6rem;
}

/* ── One card ───────────────────────────────────────────────────────── */
.card-item {
  margin-bottom: 1.2rem;
  padding: 1.2rem 1.3rem;
  border: 1px solid #e3e7ee;
  border-radius: 0.9rem;
}

.card-item-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.8rem;
}

.card-item-title {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.card-type {
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
  font-weight: 700;
  word-break: break-word;
}

.card-supplier {
  color: #545f71;
  font-size: 0.85rem;
  font-weight: 600;
}

.card-item-actions {
  display: inline-flex;
  gap: 0.4rem;
  flex-shrink: 0;
}

.card-uniqueness {
  margin: 0.55rem 0 0;
  padding: 0.6rem 0.8rem;
  background: #f7f8fb;
  border-radius: 0.55rem;
  color: #545f71;
  font-size: 0.9rem;
  white-space: pre-line;
}

/* ── Orders ─────────────────────────────────────────────────────────── */
.orders-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
  margin-top: 1rem;
}

.orders-head h4 {
  margin: 0;
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.order-form {
  display: flex;
  align-items: flex-end;
  gap: 0.8rem;
  flex-wrap: wrap;
  margin-top: 0.7rem;
  padding: 0.9rem 1rem;
  background: #f7f8fb;
  border-radius: 0.7rem;
}

.order-form .field:first-child {
  flex: 1 1 220px;
}

.order-form-actions {
  display: flex;
  gap: 0.4rem;
  padding-bottom: 0.1rem;
}

.order-error {
  flex-basis: 100%;
  margin: 0;
}

.order-table {
  width: 100%;
  margin-top: 0.7rem;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.order-table th {
  padding: 0.45rem 0.6rem;
  border-bottom: 2px solid #e3e7ef;
  color: #8b94a6;
  font-size: 0.74rem;
  font-weight: 700;
  text-align: left;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}

.order-table td {
  padding: 0.55rem 0.6rem;
  border-bottom: 1px solid #eef1f6;
  color: var(--login-secondary, #0c1c40);
  vertical-align: middle;
}

.order-table .num {
  text-align: right;
  white-space: nowrap;
}

.order-product {
  font-weight: 600;
}

.col-actions {
  text-align: right;
  white-space: nowrap;
}

.row-actions {
  display: inline-flex;
  gap: 0.4rem;
}

/* ── Workflow status colours ────────────────────────────────────────── */
.status-select :deep(.app-select-toggle) {
  border: none;
  font-weight: 700;
}

.status-select--quote :deep(.app-select-toggle) {
  background: #eef1f6;
  color: #545f71;
}

.status-select--ordered :deep(.app-select-toggle) {
  background: #e7eefc;
  color: #2b59c3;
}

.status-select--proforma :deep(.app-select-toggle) {
  background: #fdf3e6;
  color: #b06414;
}

.status-select--proforma_paid :deep(.app-select-toggle) {
  background: #f0e9fb;
  color: #7048b6;
}

.status-select--procurement :deep(.app-select-toggle) {
  background: #fbe7f3;
  color: #b3127c;
}

.status-select--shipping :deep(.app-select-toggle) {
  background: #e0f4f6;
  color: #0e7c86;
}

.status-select--paid :deep(.app-select-toggle) {
  background: #e3f6ec;
  color: #1c7a45;
}

/* ── Kanban board ───────────────────────────────────────────────────── */
.kanban-wrap {
  margin-bottom: 1.5rem;
}

.kanban-title {
  margin: 0 0 0.2rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.05rem;
  font-weight: 700;
}

.kanban-hint {
  margin: 0 0 0.8rem;
  color: #8b94a6;
  font-size: 0.82rem;
}

.kanban {
  display: grid;
  grid-template-columns: repeat(7, minmax(150px, 1fr));
  gap: 0.7rem;
  overflow-x: auto;
  padding-bottom: 0.4rem;
}

.kanban-col {
  display: flex;
  flex-direction: column;
  min-width: 150px;
  background: #f7f8fb;
  border-radius: 0.8rem;
  transition: outline-color 0.1s;
  outline: 2px dashed transparent;
  outline-offset: -2px;
}

.kanban-col.is-over {
  outline-color: var(--login-primary, #ed2044);
  background: #fdf2f4;
}

.kanban-col-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.4rem;
  padding: 0.55rem 0.75rem;
  border-radius: 0.8rem 0.8rem 0 0;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.kanban-col-head--quote { background: #eef1f6; color: #545f71; }
.kanban-col-head--ordered { background: #e7eefc; color: #2b59c3; }
.kanban-col-head--proforma { background: #fdf3e6; color: #b06414; }
.kanban-col-head--proforma_paid { background: #f0e9fb; color: #7048b6; }
.kanban-col-head--procurement { background: #fbe7f3; color: #b3127c; }
.kanban-col-head--shipping { background: #e0f4f6; color: #0e7c86; }
.kanban-col-head--paid { background: #e3f6ec; color: #1c7a45; }

.kanban-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.35rem;
  height: 1.35rem;
  padding: 0 0.3rem;
  background: rgba(255, 255, 255, 0.75);
  border-radius: 0.7rem;
  font-size: 0.72rem;
}

.kanban-col-body {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  flex: 1 1 auto;
  min-height: 3.2rem;
  padding: 0.55rem;
}

.kanban-item {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0.6rem 0.7rem;
  background: #fff;
  border-radius: 0.6rem;
  box-shadow: 0 3px 10px rgba(12, 28, 64, 0.08);
  cursor: grab;
  transition: transform 0.1s, box-shadow 0.1s;
}

.kanban-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 14px rgba(12, 28, 64, 0.12);
}

.kanban-item.is-dragging {
  opacity: 0.45;
}

.kanban-item-product {
  color: var(--login-secondary, #0c1c40);
  font-size: 0.86rem;
  font-weight: 700;
  word-break: break-word;
}

.kanban-item-meta {
  color: #545f71;
  font-size: 0.76rem;
  font-weight: 600;
  word-break: break-word;
}

.kanban-item-total {
  color: var(--login-primary, #ed2044);
  font-size: 0.82rem;
  font-weight: 700;
}

.kanban-item-date {
  color: #8b94a6;
  font-size: 0.72rem;
  font-weight: 600;
}

.order-total {
  font-weight: 700;
}

.order-table tfoot td {
  border-bottom: none;
  border-top: 2px solid #e3e7ef;
  font-weight: 700;
}

.margin-pos {
  color: #1c7a45;
  font-weight: 700;
}

.margin-neg {
  color: #b3122e;
  font-weight: 700;
}

.order-preview {
  flex-basis: 100%;
  margin: 0;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
}

.muted {
  margin: 0;
  color: #8b94a6;
  font-size: 0.9rem;
}

.muted--small {
  margin-top: 0.6rem;
  font-size: 0.85rem;
}

.msg {
  margin: 0 0 0.9rem;
  padding: 0.65rem 0.85rem;
  border-radius: 0.55rem;
  font-size: 0.9rem;
}

.msg--error {
  background: #fde8ec;
  color: #b3122e;
}

.btn-submit {
  padding: 0.6rem 1.3rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.55rem;
  color: #fff;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-submit:disabled {
  opacity: 0.65;
  cursor: progress;
}

.btn-ghost {
  padding: 0.5rem 1.1rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
}

.btn-mini {
  padding: 0.4rem 0.8rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.45rem;
  color: #fff;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.btn-mini:disabled {
  opacity: 0.65;
  cursor: progress;
}

.btn-mini--ghost {
  background: #fff;
  border: 1px solid #d4dae6;
  color: var(--login-secondary, #0c1c40);
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.9rem;
  height: 1.9rem;
  padding: 0;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: #545f71;
  cursor: pointer;
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

@media (max-width: 767.98px) {
  .card-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
