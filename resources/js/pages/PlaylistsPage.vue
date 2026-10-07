<script setup>
import { computed, onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import { useToast } from 'vue-toastification'
import * as yup from 'yup'
import { usePlaylistStore } from '@/store/playlist'
import { confirm } from '@/plugins/confirm'
import { errorText } from '@/utils/feedback'
import { $api } from '@/utils/api'
import { scopedQuery } from '@/utils/query'
import AmsListPage from '@/components/AmsListPage.vue'
import AmsDataTable from '@/components/AmsDataTable.vue'

const store = usePlaylistStore()
const toast = useToast()
const showModal = ref(false)
const editing = ref(null)
const filterName = ref('')
const formError = ref('')
const draftItems = ref([])
const rowCount = ref(1)
const colCount = ref(1)
const columnDuration = ref({})
const uploadingCell = ref('')

const schema = yup.object({
  name: yup.string().trim().required('Name cannot be empty'),
})
const { handleSubmit, errors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: { name: '' },
})
const { value: name } = useField('name')

let nextItemKey = 1
const rowIndexes = computed(() => Array.from({ length: rowCount.value }, (_, index) => index))
const colIndexes = computed(() => Array.from({ length: colCount.value }, (_, index) => index))

onMounted(() => store.refreshList())

function openCreate() {
  editing.value = null
  draftItems.value = []
  rowCount.value = 1
  colCount.value = 1
  columnDuration.value = { 0: 10000 }
  formError.value = ''
  resetForm({ values: { name: '' } })
  showModal.value = true
}

function openEdit(item) {
  editing.value = item
  applyDraft(item)
  formError.value = ''
  resetForm({ values: { name: item.name } })
  showModal.value = true
}

function applyDraft(playlist) {
  const items = (playlist.items || []).map(entry => ({
    key: nextItemKey++,
    media_asset_id: entry.media_asset_id,
    name: entry.name,
    type: entry.type,
    status: entry.status,
    row: Number(entry.row_index || 0),
    col: Number(entry.column_index || 0),
    duration_ms: Number(entry.duration_ms || 10000),
  }))
  draftItems.value = items
  rowCount.value = Math.max(1, ...items.map(item => item.row + 1), 1)
  colCount.value = Math.max(1, ...items.map(item => item.col + 1), 1)
  const durations = {}
  for (let col = 0; col < colCount.value; col += 1) durations[col] = 10000
  items.filter(item => item.type !== 'video').forEach(item => {
    durations[item.col] = item.duration_ms
  })
  columnDuration.value = durations
}

function cell(row, col) {
  return draftItems.value.find(item => item.row === row && item.col === col)
}

function columnHasImage(col) {
  return draftItems.value.some(item => item.col === col && item.type !== 'video')
}

function addScreen() {
  rowCount.value += 1
}

function queueContent() {
  columnDuration.value = { ...columnDuration.value, [colCount.value]: 10000 }
  colCount.value += 1
}

function removeRow(row) {
  draftItems.value = draftItems.value
    .filter(item => item.row !== row)
    .map(item => (item.row > row ? { ...item, row: item.row - 1 } : item))
  rowCount.value = Math.max(1, rowCount.value - 1)
}

function removeColumn(col) {
  draftItems.value = draftItems.value
    .filter(item => item.col !== col)
    .map(item => (item.col > col ? { ...item, col: item.col - 1 } : item))
  const durations = {}
  Object.entries(columnDuration.value).forEach(([key, value]) => {
    const index = Number(key)
    if (index < col) durations[index] = value
    if (index > col) durations[index - 1] = value
  })
  colCount.value = Math.max(1, colCount.value - 1)
  if (durations[colCount.value - 1] == null) durations[colCount.value - 1] = 10000
  columnDuration.value = durations
}

function removeCell(row, col) {
  draftItems.value = draftItems.value.filter(item => !(item.row === row && item.col === col))
}

async function uploadInto(row, col, event) {
  const chosen = event.target.files?.[0]
  event.target.value = ''
  if (!editing.value || !chosen) {
    formError.value = 'Choose a file.'
    return
  }
  formError.value = ''
  const key = `${row}-${col}`
  uploadingCell.value = key
  try {
    const formData = new FormData()
    formData.append('file', chosen)
    formData.append('row_index', String(row))
    formData.append('column_index', String(col))
    formData.append('duration_ms', String(columnDuration.value[col] || 10000))
    const scope = scopedQuery()
    if (scope.organization_id) formData.append('organization_id', scope.organization_id)
    for (const query of scope['queries[]'] || []) formData.append('queries[]', query)
    const response = await $api.raw(`/api/playlists/${editing.value.id}/items`, { method: 'POST', body: formData })
    const saved = (response._data?.items || []).find(item => item.row_index === row && item.column_index === col)
    if (!saved) {
      formError.value = 'The file did not land in that cell.'
      return
    }
    const next = {
      key: nextItemKey++,
      media_asset_id: saved.media_asset_id,
      name: saved.name,
      type: saved.type,
      status: saved.status,
      row,
      col,
      duration_ms: Number(saved.duration_ms || columnDuration.value[col] || 10000),
    }
    draftItems.value = draftItems.value.filter(item => !(item.row === row && item.col === col))
    draftItems.value.push(next)
    if (next.type !== 'video') {
      columnDuration.value = { ...columnDuration.value, [col]: next.duration_ms }
    }
    await store.refreshList()
    toast.success('Media added')
  } catch (error) {
    formError.value = errorText(error, 'Could not add the file.')
  } finally {
    uploadingCell.value = ''
  }
}

const submit = handleSubmit(async (values) => {
  formError.value = ''
  const wasEditing = Boolean(editing.value)
  try {
    if (!editing.value) {
      editing.value = await store.create({ name: values.name })
      applyDraft(editing.value)
      toast.success('Playlist created. Add the files here.')
      return
    }
    await store.update({ id: editing.value.id, name: values.name })
    await store.saveItems(editing.value.id, draftItems.value.map(item => ({
      media_asset_id: item.media_asset_id,
      row_index: item.row,
      column_index: item.col,
      duration_ms: item.type === 'video' ? Number(item.duration_ms) : Number(columnDuration.value[item.col] || item.duration_ms),
    })))
    showModal.value = false
    toast.success(wasEditing ? 'Playlist updated' : 'Playlist created')
  } catch (error) {
    formError.value = errorText(error, 'Could not save the playlist.')
  }
})

async function publish(item) {
  try {
    await store.publish(item.id)
    toast.success('Playlist published')
  } catch (error) {
    toast.error(errorText(error, 'Could not publish.'))
  }
}

async function applyFilter() {
  store.query = filterName.value ? [{ field: 'name', value: filterName.value }] : []
  await store.refreshList()
}

async function resetFilter() {
  filterName.value = ''
  store.query = []
  await store.refreshList()
}

async function remove(item) {
  const accepted = await confirm({
    title: 'Delete playlist',
    text: `Delete ${item.name}?`,
    confirmText: 'Delete',
    confirmColor: 'error',
  })
  if (!accepted) return
  try {
    await store.remove(item.id)
    toast.success('Playlist deleted')
  } catch (error) {
    toast.error(errorText(error, 'Could not delete the playlist.'))
  }
}
</script>

<template>
  <AmsListPage
    v-model:filter="filterName"
    add-label="Add Playlist"
    filter-placeholder="Enter playlist name"
    :loading="store.getIsLoading"
    @submit="applyFilter"
    @reset="resetFilter"
    @add="openCreate"
  >
    <AmsDataTable
      :loading="store.getIsLoading"
      :items="store.getList"
      :headers="[
        { title: 'Action', key: 'actions', sortable: false, width: 160 },
        { title: 'Name', key: 'name' },
        { title: 'Draft / live', key: 'draft_vs_live' },
      ]"
    >
      <template #item.draft_vs_live="{ item }">
        {{ item.draft_item_count }} draft / {{ item.published_item_count }} live
        <v-chip v-if="!item.published_generation" size="small" class="ms-2">Not published</v-chip>
        <v-chip v-else-if="item.unpublished_changes" size="small" color="warning" class="ms-2">Unpublished changes</v-chip>
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn color="primary" aria-label="Edit" @click="openEdit(item)">
            <VIcon icon="tabler-pencil" />
            <VTooltip activator="parent">Edit</VTooltip>
          </IconBtn>
          <IconBtn color="primary" aria-label="Publish" @click="publish(item)">
            <VIcon icon="tabler-send" />
            <VTooltip activator="parent">Publish</VTooltip>
          </IconBtn>
          <IconBtn color="error" aria-label="Delete" @click="remove(item)">
            <VIcon icon="tabler-trash" />
            <VTooltip activator="parent">Delete</VTooltip>
          </IconBtn>
        </div>
      </template>
    </AmsDataTable>
    <template #dialog>
      <VDialog v-model="showModal" max-width="960">
        <VCard>
          <VCardTitle class="text-h5 pt-6 px-6">{{ editing ? 'Edit Playlist' : 'Add Playlist' }}</VCardTitle>
          <VCardText>
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-3" :text="formError" />
            <label>Name:</label>
            <VTextField v-model="name" placeholder="Enter playlist name" :error-messages="errors.name" />
            <template v-if="editing">
              <div class="d-flex justify-space-between align-center mb-3" style="gap: 12px">
                <p class="text-body-2 mb-0">Each row is one screen. Queue content adds the next item on that screen.</p>
                <div class="d-flex" style="gap: 8px">
                  <VBtn size="small" variant="outlined" @click="addScreen">Add screen</VBtn>
                  <VBtn size="small" variant="outlined" @click="queueContent">Queue content</VBtn>
                </div>
              </div>
              <div style="overflow-x: auto">
                <table class="playlist-grid">
                  <thead>
                    <tr>
                      <th />
                      <th v-for="col in colIndexes" :key="`col-${col}`">
                        <div class="d-flex align-center" style="gap: 4px">
                          <span>Content {{ col + 1 }}</span>
                          <IconBtn v-if="colCount > 1" aria-label="Remove column" @click="removeColumn(col)">
                            <VIcon icon="tabler-x" size="16" />
                          </IconBtn>
                        </div>
                        <VTextField
                          v-if="columnHasImage(col)"
                          v-model.number="columnDuration[col]"
                          type="number"
                          label="Picture ms"
                          density="compact"
                          hide-details
                          class="mt-2"
                          style="min-width: 140px"
                        />
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in rowIndexes" :key="`row-${row}`">
                      <th>
                        <div class="d-flex align-center" style="gap: 4px">
                          <span>Screen {{ row + 1 }}</span>
                          <IconBtn v-if="rowCount > 1" aria-label="Remove screen" @click="removeRow(row)">
                            <VIcon icon="tabler-x" size="16" />
                          </IconBtn>
                        </div>
                      </th>
                      <td v-for="col in colIndexes" :key="`cell-${row}-${col}`">
                        <div v-if="cell(row, col)" class="d-flex align-center" style="gap: 8px">
                          <span>{{ cell(row, col).name }}</span>
                          <VChip size="small" variant="tonal">{{ cell(row, col).status }}</VChip>
                          <IconBtn aria-label="Remove media" @click="removeCell(row, col)">
                            <VIcon icon="tabler-x" />
                          </IconBtn>
                        </div>
                        <label v-else class="text-primary" style="cursor: pointer">
                          {{ uploadingCell === `${row}-${col}` ? 'Uploading…' : 'Upload' }}
                          <input
                            type="file"
                            class="d-none"
                            accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime"
                            :disabled="uploadingCell !== ''"
                            @change="uploadInto(row, col, $event)"
                          >
                        </label>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p class="text-body-2 mt-3 mb-0">A picture uses the column time. A longer video in that column keeps every screen on it until the video ends. An empty cell holds the previous picture or the last frame.</p>
            </template>
          </VCardText>
          <VCardActions class="px-6 pb-6">
            <VSpacer />
            <VBtn variant="outlined" @click="showModal = false">Cancel</VBtn>
            <VBtn variant="elevated" @click="submit">{{ editing ? 'Save' : 'Create' }}</VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </template>
  </AmsListPage>
</template>

<style scoped>
.playlist-grid {
  width: 100%;
  border-collapse: collapse;
}

.playlist-grid th,
.playlist-grid td {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding: 12px;
  vertical-align: top;
  min-width: 180px;
}
</style>
