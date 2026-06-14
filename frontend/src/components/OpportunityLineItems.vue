<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatMoney, type LineItem, type Currency } from '@/stores/opportunities'
import { useProductsStore } from '@/stores/products'

const props = defineProps<{ lineItems: LineItem[]; currency: Currency; total?: string }>()

const { t } = useI18n()
const productsStore = useProductsStore()

// Fall back to summing the lines when no explicit offer total is passed.
const totalValue = computed<string>(
  () => props.total ?? props.lineItems.reduce((sum, li) => sum + Number(li.lineTotal), 0).toFixed(2),
)

/** "Category › Subcategory" for a catalogue line; empty for custom lines. */
function lineCategoryPath(li: LineItem): string {
  if (null === li.productId) return ''
  const product = productsStore.products.find((p) => p.id === li.productId)
  if (!product) return ''
  return [product.categoryName, product.subcategoryName].filter(Boolean).join(' › ')
}

/** A line shows its unit price split into material + fee (hardware). */
function lineHasSplit(li: LineItem): boolean {
  return null !== li.materialUnitPrice || null !== li.feeUnitPrice
}
</script>

<template>
  <div class="opp-lines">
    <div class="opp-lines-head">
      <span>{{ t('adminCustomers.oppProduct') }}</span>
      <span class="ta-right">{{ t('adminCustomers.oppQuantity') }}</span>
      <span class="ta-right">{{ t('adminCustomers.oppUnitPrice') }}</span>
      <span class="ta-right">{{ t('adminCustomers.oppLineTotal') }}</span>
    </div>
    <div v-for="li in lineItems" :key="li.id" class="opp-line">
      <div class="opp-line-product">
        <span v-if="lineCategoryPath(li)" class="opp-line-cat">{{ lineCategoryPath(li) }}</span>
        <span class="opp-line-name">{{ li.productName }}</span>
      </div>
      <span class="ta-right opp-line-qty">{{ li.quantity }}</span>
      <div class="ta-right opp-line-price">
        <template v-if="lineHasSplit(li)">
          <span class="opp-line-part">{{ t('adminCustomers.oppMaterialShort') }}: {{ formatMoney(li.materialUnitPrice ?? '0', currency) }}</span>
          <span class="opp-line-part">{{ t('adminCustomers.oppFeeShort') }}: {{ formatMoney(li.feeUnitPrice ?? '0', currency) }}</span>
          <span class="opp-line-sum">= {{ formatMoney(li.unitPrice, currency) }}</span>
        </template>
        <template v-else>{{ formatMoney(li.unitPrice, currency) }}</template>
      </div>
      <span class="ta-right opp-line-total">{{ formatMoney(li.lineTotal, currency) }}</span>
    </div>
    <div class="opp-line opp-line--total">
      <span class="opp-line-total-label">{{ t('adminCustomers.oppLineItemsTotal') }}</span>
      <strong>{{ formatMoney(totalValue, currency) }}</strong>
    </div>
  </div>
</template>

<style scoped>
.opp-lines {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  padding: 0.5rem 1rem 0.9rem 2.3rem;
}

.opp-lines-head,
.opp-line {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 5rem 11rem 8rem;
  gap: 0.6rem;
  align-items: start;
}

.opp-lines-head {
  color: #8b94a6;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.opp-line {
  color: #545f71;
  font-size: 0.85rem;
}

.opp-line-product {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  min-width: 0;
}

.opp-line-cat {
  color: #8b94a6;
  font-size: 0.72rem;
  font-weight: 700;
}

.opp-line-name {
  color: var(--login-secondary, #0c1c40);
  font-weight: 600;
  word-break: break-word;
}

.opp-line-qty {
  font-variant-numeric: tabular-nums;
}

.opp-line-price {
  display: flex;
  flex-direction: column;
  gap: 0.05rem;
}

.opp-line-part {
  color: #8b94a6;
  font-size: 0.78rem;
}

.opp-line-sum {
  color: var(--login-secondary, #0c1c40);
  font-weight: 700;
}

.opp-line-total {
  color: var(--login-secondary, #0c1c40);
  font-weight: 700;
}

.opp-line--total {
  grid-template-columns: 1fr auto;
  margin-top: 0.2rem;
  padding-top: 0.45rem;
  border-top: 1px dashed #d4dae6;
}

.opp-line-total-label {
  color: #545f71;
  font-weight: 700;
}

.opp-line--total strong {
  color: var(--login-primary, #ed2044);
}

.ta-right {
  text-align: right;
}
</style>
