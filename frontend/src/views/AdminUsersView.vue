<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useUsersStore, type NewUser, type UserRole } from '@/stores/users'
import { useAuthStore, type AuthUser } from '@/stores/auth'
import AppSelect from '@/components/AppSelect.vue'

const { t } = useI18n()
const store = useUsersStore()
const auth = useAuthStore()
const { users, loading, error } = storeToRefs(store)

const roleSelectOptions = computed<{ value: UserRole; label: string }[]>(() => [
  { value: 'user', label: t('adminUsers.roleUser') },
  { value: 'sales', label: t('adminUsers.roleSales') },
  { value: 'sales_manager', label: t('adminUsers.roleSalesManager') },
  { value: 'admin', label: t('adminUsers.roleAdmin') },
])

const form = reactive<NewUser>({
  email: '',
  firstName: '',
  lastName: '',
  password: '',
  role: 'user',
})

const submitting = ref(false)
const formError = ref<string | null>(null)
const formSuccess = ref<string | null>(null)

// Admin password-reset modal state.
const passwordTarget = ref<AuthUser | null>(null)
const pwValue = ref('')
const pwError = ref<string | null>(null)
const pwSubmitting = ref(false)
const listNotice = ref<string | null>(null)

onMounted(() => {
  store.fetchUsers()
})

function resetForm() {
  form.email = ''
  form.firstName = ''
  form.lastName = ''
  form.password = ''
  form.role = 'user'
}

async function onCreate() {
  formError.value = null
  formSuccess.value = null
  submitting.value = true

  const result = await store.createUser({
    email: form.email.trim(),
    firstName: form.firstName.trim(),
    lastName: form.lastName.trim(),
    password: form.password,
    role: form.role,
  })

  submitting.value = false

  if (result.ok) {
    formSuccess.value = t('adminUsers.created')
    resetForm()
  } else {
    formError.value = result.error ?? t('adminUsers.createFailed')
  }
}

function roleLabel(role: UserRole): string {
  const labels: Record<UserRole, string> = {
    user: t('adminUsers.roleUser'),
    sales: t('adminUsers.roleSales'),
    sales_manager: t('adminUsers.roleSalesManager'),
    admin: t('adminUsers.roleAdmin'),
  }
  return labels[role]
}

async function onChangeRole(user: AuthUser, role: UserRole) {
  if (role === user.role) return
  listNotice.value = null
  const result = await store.updateRole(user.id, role)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.saveFailed'))
  } else {
    listNotice.value = t('adminUsers.roleUpdated', { name: user.fullName })
  }
}

async function onDelete(id: number, name: string) {
  if (!window.confirm(t('adminUsers.confirmDelete', { name }))) return

  const result = await store.deleteUser(id)
  if (!result.ok) {
    window.alert(result.error ?? t('admin.deleteFailed'))
  }
}

function openPasswordModal(user: AuthUser) {
  passwordTarget.value = user
  pwValue.value = ''
  pwError.value = null
  listNotice.value = null
}

function closePasswordModal() {
  passwordTarget.value = null
}

async function onSetPassword() {
  if (!passwordTarget.value) return

  if (pwValue.value.length < 8) {
    pwError.value = t('account.errMinLength')
    return
  }

  pwError.value = null
  pwSubmitting.value = true
  const result = await store.setPassword(passwordTarget.value.id, pwValue.value)
  pwSubmitting.value = false

  if (result.ok) {
    listNotice.value = t('adminUsers.passwordUpdated', { name: passwordTarget.value.fullName })
    passwordTarget.value = null
  } else {
    pwError.value = result.error ?? t('adminUsers.setPasswordFailed')
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('hu-HU', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  })
}
</script>

<template>
  <section class="admin">
    <div class="container-lg">
      <div class="admin-head">
        <span class="eyebrow">{{ t('admin.eyebrow') }}</span>
        <h1>{{ t('nav.adminUsers') }}</h1>
        <p>{{ t('adminUsers.subtitle') }}</p>
      </div>

      <div class="admin-grid">
        <!-- ── Create form ───────────────────────────────────────── -->
        <form class="au-panel create-form" @submit.prevent="onCreate">
          <h2>{{ t('adminUsers.newUser') }}</h2>

          <label class="field">
            <span class="field-label">{{ t('adminUsers.lastName') }}</span>
            <input v-model="form.lastName" type="text" required />
          </label>
          <label class="field">
            <span class="field-label">{{ t('adminUsers.firstName') }}</span>
            <input v-model="form.firstName" type="text" required />
          </label>
          <label class="field">
            <span class="field-label">{{ t('login.email') }}</span>
            <input
              v-model="form.email"
              type="email"
              required
              :placeholder="t('adminUsers.emailPlaceholder')"
            />
          </label>
          <label class="field">
            <span class="field-label">{{ t('login.password') }}</span>
            <input
              v-model="form.password"
              type="text"
              required
              minlength="8"
              :placeholder="t('account.minChars')"
            />
          </label>
          <label class="field">
            <span class="field-label">{{ t('adminUsers.role') }}</span>
            <AppSelect v-model="form.role" :options="roleSelectOptions" />
          </label>

          <p v-if="formError" class="msg msg--error">{{ formError }}</p>
          <p v-if="formSuccess" class="msg msg--success">{{ formSuccess }}</p>

          <button type="submit" class="btn-submit" :disabled="submitting">
            {{ submitting ? t('admin.creating') : t('adminUsers.createUser') }}
          </button>
        </form>

        <!-- ── User list ─────────────────────────────────────────── -->
        <div class="au-panel user-list">
          <h2>{{ t('adminUsers.existing') }}</h2>

          <p v-if="listNotice" class="msg msg--success">{{ listNotice }}</p>

          <p v-if="loading" class="state">{{ t('adminUsers.loading') }}</p>

          <div v-else-if="error" class="state state--error">
            <strong>{{ t('adminUsers.loadError') }}</strong>
            <span>{{ error }}</span>
            <button type="button" class="btn-retry" @click="store.fetchUsers()">
              {{ t('common.retry') }}
            </button>
          </div>

          <p v-else-if="users.length === 0" class="state">{{ t('adminUsers.empty') }}</p>

          <table v-else class="users-table">
            <thead>
              <tr>
                <th>{{ t('adminUsers.colName') }}</th>
                <th>{{ t('adminUsers.colEmail') }}</th>
                <th>{{ t('adminUsers.role') }}</th>
                <th>{{ t('adminUsers.colCreated') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in users" :key="u.id">
                <td :data-label="t('adminUsers.colName')">{{ u.fullName }}</td>
                <td :data-label="t('adminUsers.colEmail')">{{ u.email }}</td>
                <td :data-label="t('adminUsers.role')">
                  <AppSelect
                    v-if="u.id !== auth.user?.id"
                    class="role-select"
                    compact
                    :model-value="u.role"
                    :options="roleSelectOptions"
                    @change="(v) => onChangeRole(u, v)"
                  />
                  <span v-else class="role" :class="{ 'role--admin': u.isAdmin }">
                    {{ roleLabel(u.role) }}
                  </span>
                </td>
                <td :data-label="t('adminUsers.colCreated')">{{ formatDate(u.createdAt) }}</td>
                <td class="cell-action">
                  <button type="button" class="btn-ghost" @click="openPasswordModal(u)">
                    {{ t('adminUsers.passwordBtn') }}
                  </button>
                  <button
                    v-if="u.id !== auth.user?.id"
                    type="button"
                    class="btn-delete"
                    @click="onDelete(u.id, u.fullName)"
                  >
                    {{ t('admin.delete') }}
                  </button>
                  <span v-else class="self-tag">{{ t('adminUsers.self') }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Admin password-reset modal ──────────────────────────────── -->
  <div v-if="passwordTarget" class="au-modal-backdrop" @click.self="closePasswordModal">
    <div class="modal-card">
      <h2>{{ t('adminUsers.setPasswordTitle') }}</h2>
      <p class="modal-sub">{{ passwordTarget.fullName }} · {{ passwordTarget.email }}</p>

      <form @submit.prevent="onSetPassword">
        <label class="field">
          <span class="field-label">{{ t('account.newPassword') }}</span>
          <input
            v-model="pwValue"
            type="text"
            required
            minlength="8"
            :placeholder="t('account.minChars')"
          />
        </label>

        <p v-if="pwError" class="msg msg--error">{{ pwError }}</p>

        <div class="modal-actions">
          <button type="button" class="btn-ghost" @click="closePasswordModal">
            {{ t('adminUsers.cancel') }}
          </button>
          <button type="submit" class="btn-submit btn-submit--inline" :disabled="pwSubmitting">
            {{ pwSubmitting ? t('admin.saving') : t('adminUsers.setPasswordBtn') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.admin {
  padding: 3.5rem 0 5rem;
}

.admin-head {
  margin-bottom: 2.4rem;
}

.eyebrow {
  display: inline-block;
  color: var(--login-primary, #ed2044);
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.admin-head h1 {
  margin: 0.35rem 0 0.5rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 2.4rem;
  font-weight: 700;
}

.admin-head p {
  margin: 0;
  color: #545f71;
  font-size: 1.05rem;
}

.admin-grid {
  display: grid;
  gap: 1.5rem;
  grid-template-columns: 320px 1fr;
  align-items: start;
}

.au-panel {
  padding: 1.85rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 12px 32px rgba(12, 28, 64, 0.08);
}

.au-panel h2 {
  margin: 0 0 1.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.25rem;
  font-weight: 700;
}

.field {
  display: block;
  margin-bottom: 1rem;
}

.field-label {
  display: block;
  margin-bottom: 0.35rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.88rem;
  font-weight: 700;
}

.field input,
.field select {
  width: 100%;
  padding: 0.65rem 0.8rem;
  border: 1px solid #d4dae6;
  border-radius: 0.55rem;
  font-size: 0.98rem;
  color: var(--login-secondary, #0c1c40);
  background: #fff;
  transition: border-color 0.15s ease;
}

.field input:focus,
.field select:focus {
  outline: none;
  border-color: var(--login-primary, #ed2044);
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

.msg--success {
  background: #e3f6ec;
  color: #1c7a45;
}

.btn-submit {
  width: 100%;
  padding: 0.75rem 1rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.55rem;
  color: #fff;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  transition: transform 0.15s ease;
}

.btn-submit:hover:not(:disabled) {
  transform: translateY(-2px);
}

.btn-submit:disabled {
  opacity: 0.65;
  cursor: progress;
}

.btn-submit--inline {
  width: auto;
  padding: 0.6rem 1.3rem;
}

.users-table {
  width: 100%;
  border-collapse: collapse;
}

.users-table th {
  padding: 0.6rem 0.7rem;
  border-bottom: 2px solid #eef1f6;
  color: #8b94a6;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  text-align: left;
}

.users-table td {
  padding: 0.8rem 0.7rem;
  border-bottom: 1px solid #eef1f6;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.95rem;
}

.role {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  background: #eef1f6;
  border-radius: 100vw;
  color: #545f71;
  font-size: 0.78rem;
  font-weight: 700;
}

.role--admin {
  background: var(--login-primary, #ed2044);
  color: #fff;
}

.role-select :deep(.app-select-toggle) {
  font-weight: 700;
}

.cell-action {
  text-align: right;
  white-space: nowrap;
}

.cell-action .btn-ghost + .btn-delete,
.cell-action .btn-ghost + .self-tag {
  margin-left: 0.4rem;
}

.btn-ghost {
  padding: 0.35rem 0.8rem;
  background: #fff;
  border: 1px solid #d4dae6;
  border-radius: 0.45rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition:
    border-color 0.15s ease,
    color 0.15s ease;
}

.btn-ghost:hover {
  border-color: var(--login-secondary, #0c1c40);
}

.btn-delete {
  padding: 0.35rem 0.8rem;
  background: #fff;
  border: 1px solid #e0a9b3;
  border-radius: 0.45rem;
  color: #b3122e;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition:
    background 0.15s ease,
    color 0.15s ease;
}

.btn-delete:hover {
  background: var(--login-primary, #ed2044);
  border-color: var(--login-primary, #ed2044);
  color: #fff;
}

.self-tag {
  color: #8b94a6;
  font-size: 0.82rem;
  font-style: italic;
}

.state {
  padding: 1.3rem;
  background: #f7f8fb;
  border-radius: 0.7rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1rem;
}

.state--error {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.6rem;
}

.btn-retry {
  padding: 0.4rem 0.9rem;
  background: var(--login-primary, #ed2044);
  border: none;
  border-radius: 0.45rem;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

/* ── Password-reset modal ──────────────────────────────────────── */
.au-modal-backdrop {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  background: rgba(12, 28, 64, 0.55);
  z-index: 1080;
}

.modal-card {
  width: 100%;
  max-width: 400px;
  padding: 2rem;
  background: #fff;
  border-radius: 1.1rem;
  box-shadow: 0 24px 56px rgba(12, 28, 64, 0.32);
}

.modal-card h2 {
  margin: 0 0 0.3rem;
  color: var(--login-secondary, #0c1c40);
  font-size: 1.3rem;
  font-weight: 700;
}

.modal-sub {
  margin: 0 0 1.3rem;
  color: #545f71;
  font-size: 0.92rem;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.6rem;
  margin-top: 0.4rem;
}

@media (max-width: 767.98px) {
  .admin-grid {
    grid-template-columns: 1fr;
  }

  .users-table,
  .users-table thead,
  .users-table tbody,
  .users-table tr,
  .users-table td {
    display: block;
  }

  .users-table thead {
    display: none;
  }

  .users-table tr {
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #eef1f6;
  }

  .users-table td {
    border: none;
    padding: 0.3rem 0;
  }

  .users-table td::before {
    content: attr(data-label) ': ';
    font-weight: 700;
    color: #8b94a6;
  }

  .cell-action {
    text-align: left;
    margin-top: 0.4rem;
  }

  .cell-action::before {
    content: '' !important;
  }
}
</style>
