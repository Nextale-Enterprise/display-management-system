<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useField, useForm } from 'vee-validate'
import { useToast } from 'vue-toastification'
import * as yup from 'yup'
import { useDeviceStore } from '@/store/device'
import { useCommonStore } from '@/store/common'
import { useBranchStore } from '@/store/branch'
import { usePlaylistStore } from '@/store/playlist'
import { confirm } from '@/plugins/confirm'
import { errorText } from '@/utils/feedback'
import AmsListPage from '@/components/AmsListPage.vue'
import AmsDataTable from '@/components/AmsDataTable.vue'
import ClaimScan from '@/components/ClaimScan.vue'
import { useDisplay } from 'vuetify'

const store = useDeviceStore()
const common = useCommonStore()
const branches = useBranchStore()
const playlists = usePlaylistStore()
const toast = useToast()
const showModal = ref(false)
const showScan = ref(false)
const { mobile } = useDisplay()
const editing = ref(null)
const filterName = ref('')
const formError = ref('')

const schema = yup.object({
  name: yup.string().trim().required('Name cannot be empty'),
  branch_id: yup.number().required('Pick a branch').typeError('Pick a branch'),
  playlist_id: yup.number().required('Pick a published playlist').typeError('Pick a published playlist'),
  screen_row: yup.number().required('Pick a screen').typeError('Pick a screen'),
})
const { handleSubmit, errors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: { name: '', playlist_id: null, screen_row: null, branch_id: null, is_head: false },
})
const { value: name } = useField('name')
const { value: playlistId } = useField('playlist_id')
const { value: screenRow } = useField('screen_row')
const { value: branchId } = useField('branch_id')
const { value: isHead } = useField('is_head')
const publishedPlaylists = computed(() => playlists.getList.filter(item => item.published_generation >= 1))
const playlistRows = computed(() => {
  const playlist = publishedPlaylists.value.find(item => item.id === playlistId.value)
  const items = playlist?.live_items || []
  const rows = [...new Set(items.map(item => item.row_index))].sort((a, b) => a - b)
  return rows.map(row => ({
    screen_row: row,
    name: `Screen ${row + 1} · ${items.filter(item => item.row_index === row).sort((a, b) => a.column_index - b.column_index).map(item => item.name).join(', ')}`,
  }))
})
const selectedBranch = computed(() => branches.getList.find(item => item.id === branchId.value))
const headTakenByOther = computed(() => {
  const headId = selectedBranch.value?.head_device_id
  return Boolean(headId) && headId !== editing.value?.id
})

watch(playlistId, () => {
  if (!playlistRows.value.some(item => item.screen_row === screenRow.value)) {
    screenRow.value = playlistRows.value[0]?.screen_row ?? null
  }
})

watch(headTakenByOther, (taken) => {
  if (taken) isHead.value = false
})

onMounted(async () => {
  await Promise.all([store.refreshList(), playlists.refreshList(), branches.refreshList()])
})

function openCreate() {
  editing.value = null
  formError.value = ''
  resetForm({ values: { name: '', playlist_id: null, screen_row: null, branch_id: null, is_head: false } })
  showModal.value = true
}

function openScan() {
  showScan.value = true
}

async function onClaimed() {
  showScan.value = false
  toast.success('TV linked')
  await store.refreshList()
}

function openEdit(item) {
  editing.value = item
  formError.value = ''
  resetForm({ values: { name: item.name, playlist_id: item.playlist_id, screen_row: item.screen_row, branch_id: item.branch_id, is_head: item.is_head } })
  showModal.value = true
}

const submit = handleSubmit(async (values) => {
  formError.value = ''
  const payload = {
    name: values.name,
    playlist_id: values.playlist_id || null,
    screen_row: values.screen_row === 0 || values.screen_row ? Number(values.screen_row) : null,
    branch_id: values.branch_id || null,
    is_head: Boolean(values.is_head) && !headTakenByOther.value,
  }
  try {
    if (editing.value) await store.update({ id: editing.value.id, ...payload })
    else await store.create(payload)
    showModal.value = false
    toast.success(editing.value ? 'Device updated' : 'Device added')
  } catch (error) {
    formError.value = errorText(error, 'Could not save the device.')
  }
})

async function applyFilter() {
  store.query = filterName.value ? [{ field: 'name', value: filterName.value }] : []
  await store.refreshList()
}

async function resetFilter() {
  filterName.value = ''
  store.query = []
  await store.refreshList()
}

async function replaceTv(item) {
  const accepted = await confirm({
    title: 'Replace TV',
    text: `Unlink ${item.name}? The name, branch, and playlist stay. Scan the next TV into this device.`,
    confirmText: 'Unlink',
    confirmColor: 'warning',
  })
  if (!accepted) return
  try {
    await store.release(item.id)
    toast.success('TV unlinked')
  } catch (error) {
    toast.error(errorText(error, 'Could not unlink the TV.'))
  }
}

async function remove(item) {
  const accepted = await confirm({
    title: 'Delete device',
    text: `Delete ${item.name}?`,
    confirmText: 'Delete',
    confirmColor: 'error',
  })
  if (!accepted) return
  try {
    await store.remove(item.id)
    toast.success('Device deleted')
  } catch (error) {
    toast.error(errorText(error, 'Could not delete the device.'))
  }
}

function seen(value) {
  return value ? new Date(value).toLocaleString() : 'never'
}

function playing(item) {
  if (!item.last_seen_at) return '—'
  if (!item.reported_playing) return 'Not playing'
  return item.reported_item || 'Playing'
}

const subscriptionLine = computed(() => {
  const org = common.organizations.find(item => String(item.id) === String(common.organizationSelected))
  if (!org) return ''
  const devices = org.device_limit == null ? `${org.devices_count} devices, no limit` : `${org.devices_count} / ${org.device_limit} devices`
  const period = org.subscription_ends_at ? `until ${org.subscription_ends_at}` : 'no end date'
  return `${devices}, ${period}`
})
</script>

<template>
  <div>
  <p v-if="subscriptionLine" class="text-body-2 text-medium-emphasis mb-3">{{ subscriptionLine }}</p>
  <AmsListPage
    v-model:filter="filterName"
    add-label="Add device"
    filter-placeholder="Enter device name"
    :loading="store.getIsLoading"
    @submit="applyFilter"
    @reset="resetFilter"
    @add="openCreate"
  >
    <div class="d-flex justify-end mb-3">
      <VBtn variant="outlined" @click="openScan">Scan TV</VBtn>
    </div>
    <AmsDataTable
      :loading="store.getIsLoading"
      :items="store.getList"
      :headers="[
        { title: 'Action', key: 'actions', sortable: false, width: 160 },
        { title: 'Name', key: 'name' },
        { title: 'Branch', key: 'branch_name' },
        { title: 'Head', key: 'is_head' },
        { title: 'Playlist', key: 'playlist_name' },
        { title: 'Screen', key: 'screen_name' },
        { title: 'TV', key: 'linked' },
        { title: 'Last seen', key: 'last_seen_at' },
        { title: 'Playing', key: 'reported_playing' },
      ]"
    >
      <template #item.branch_name="{ item }">{{ item.branch_name || '—' }}</template>
      <template #item.is_head="{ item }">{{ item.is_head ? 'Head' : '—' }}</template>
      <template #item.linked="{ item }">{{ item.linked ? 'Linked' : 'Not linked' }}</template>
      <template #item.last_seen_at="{ item }">{{ seen(item.last_seen_at) }}</template>
      <template #item.reported_playing="{ item }">{{ playing(item) }}</template>
      <template #item.playlist_name="{ item }">
        {{ item.playlist_name || '—' }}
        <v-chip v-if="item.playlist_id && !item.playlist_live" size="small" color="warning" class="ms-2">Not published</v-chip>
      </template>
      <template #item.screen_name="{ item }">{{ item.screen_name || '—' }}</template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn color="primary" aria-label="Edit" @click="openEdit(item)">
            <VIcon icon="tabler-pencil" />
            <VTooltip activator="parent">Edit</VTooltip>
          </IconBtn>
          <IconBtn v-if="item.linked" color="warning" aria-label="Replace TV" @click="replaceTv(item)">
            <VIcon icon="tabler-unlink" />
            <VTooltip activator="parent">Replace TV</VTooltip>
          </IconBtn>
          <IconBtn color="error" aria-label="Delete" @click="remove(item)">
            <VIcon icon="tabler-trash" />
            <VTooltip activator="parent">Delete</VTooltip>
          </IconBtn>
        </div>
      </template>
    </AmsDataTable>
    <template #dialog>
      <VDialog v-model="showScan" :fullscreen="mobile" max-width="480">
        <VCard>
          <VCardTitle class="text-h5 pt-6 px-6 d-flex align-center">
            Scan TV
            <VSpacer />
            <VBtn icon variant="text" aria-label="Close" @click="showScan = false">
              <VIcon icon="tabler-x" />
            </VBtn>
          </VCardTitle>
          <VCardText>
            <ClaimScan v-if="showScan" @accepted="onClaimed" />
          </VCardText>
        </VCard>
      </VDialog>
      <VDialog v-model="showModal">
        <VCard>
          <VCardTitle class="text-h5 pt-6 px-6">{{ editing ? 'Edit Device' : 'Add device' }}</VCardTitle>
          <VCardText>
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-3" :text="formError" />
            <label>Name:</label>
            <VTextField v-model="name" placeholder="Enter device name" :error-messages="errors.name" />
            <label>Branch:</label>
            <VSelect
              v-model="branchId"
              :items="branches.getList"
              item-title="name"
              item-value="id"
              placeholder="Pick a branch"
            />
            <VCheckbox
              v-model="isHead"
              label="This device is the head"
              :disabled="!branchId || headTakenByOther"
              hide-details
            />
            <p v-if="headTakenByOther" class="text-body-2 mb-4">
              {{ selectedBranch.head_device_name }} is already the head of this branch.
            </p>
            <p v-if="errors.branch_id" class="text-error text-body-2">{{ errors.branch_id }}</p>
            <p v-if="errors.playlist_id" class="text-error text-body-2">{{ errors.playlist_id }}</p>
            <label>Playlist:</label>
            <VSelect
              v-model="playlistId"
              :items="publishedPlaylists"
              item-title="name"
              item-value="id"
              placeholder="Pick a published playlist"
            />
            <p v-if="errors.screen_row" class="text-error text-body-2">{{ errors.screen_row }}</p>
            <label>Screen:</label>
            <VSelect
              v-model="screenRow"
              :items="playlistRows"
              item-title="name"
              item-value="screen_row"
              :disabled="!playlistId"
              placeholder="Pick a screen"
            />
          </VCardText>
          <VCardActions class="px-6 pb-6">
            <VSpacer />
            <VBtn variant="outlined" @click="showModal = false">Cancel</VBtn>
            <VBtn variant="elevated" @click="submit">Save</VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </template>
  </AmsListPage>
  </div>
</template>
