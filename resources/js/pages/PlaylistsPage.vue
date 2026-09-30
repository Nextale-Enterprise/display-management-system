<script setup>
import { computed, onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import draggable from 'vuedraggable'
import * as yup from 'yup'
import { useMediaStore } from '@/store/media'
import { usePlaylistStore } from '@/store/playlist'
import AmsListPage from '@/components/AmsListPage.vue'

const store = usePlaylistStore()
const media = useMediaStore()
const showModal = ref(false)
const editing = ref(null)
const filterName = ref('')
const formError = ref('')
const draftItems = ref([])
const addAssetId = ref(null)

const schema = yup.object({
  name: yup.string().trim().required('Name cannot be empty'),
})
const { handleSubmit, errors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: { name: '' },
})
const { value: name } = useField('name')

const readyMedia = computed(() => media.getList)

onMounted(async () => {
  await Promise.all([store.refreshList(), media.refreshList()])
})

function openCreate() {
  editing.value = null
  draftItems.value = []
  formError.value = ''
  resetForm({ values: { name: '' } })
  showModal.value = true
}

function openEdit(item) {
  editing.value = item
  draftItems.value = (item.items || []).map(entry => ({
    media_asset_id: entry.media_asset_id,
    name: entry.name,
    status: entry.status,
    duration_ms: entry.duration_ms,
  }))
  formError.value = ''
  resetForm({ values: { name: item.name } })
  showModal.value = true
}

function addItem() {
  const asset = readyMedia.value.find(entry => entry.id === addAssetId.value)
  if (!asset) return
  draftItems.value.push({
    media_asset_id: asset.id,
    name: asset.name,
    status: asset.status,
    duration_ms: asset.duration_ms || 10000,
  })
  addAssetId.value = null
}

const submit = handleSubmit(async (values) => {
  formError.value = ''
  try {
    if (!editing.value) {
      await store.create({ name: values.name })
      showModal.value = false
      return
    }
    await store.update({ id: editing.value.id, name: values.name })
    await store.saveItems(editing.value.id, draftItems.value.map(item => ({
      media_asset_id: item.media_asset_id,
      duration_ms: Number(item.duration_ms),
    })))
    showModal.value = false
  } catch (error) {
    formError.value = error?.data?.message || 'Could not save the playlist.'
  }
})

async function publish(item) {
  formError.value = ''
  try {
    await store.publish(item.id)
  } catch (error) {
    formError.value = error?.data?.message || 'Could not publish.'
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
</script>

<template>
  <AmsListPage
    v-model:filter="filterName"
    add-label="Add Playlist"
    filter-placeholder="Enter playlist name"
    @submit="applyFilter"
    @reset="resetFilter"
    @add="openCreate"
  >
    <v-alert v-if="formError && !showModal" type="error" class="mb-4" :text="formError" />
    <v-data-table
      :loading="store.getIsLoading"
      :items="store.getList"
      :headers="[
        { title: 'Name', key: 'name' },
        { title: 'Published', key: 'published_generation' },
        { title: '', key: 'actions', sortable: false },
      ]"
    >
      <template #item.actions="{ item }">
        <v-btn variant="outlined" color="primary" icon="mdi-pencil" size="small" class="me-2" @click="openEdit(item)" />
        <v-btn variant="outlined" size="small" class="me-2" @click="publish(item)">Publish</v-btn>
        <v-btn variant="outlined" color="error" icon="mdi-delete" size="small" @click="store.remove(item.id)" />
      </template>
    </v-data-table>
    <template #dialog>
      <v-dialog v-model="showModal" max-width="600" persistent>
        <v-card>
          <v-card-title>{{ editing ? 'Edit Playlist' : 'Add Playlist' }}</v-card-title>
          <v-card-text>
            <v-alert v-if="formError" type="error" class="mb-3" :text="formError" />
            <label>Name:</label>
            <v-text-field v-model="name" placeholder="Enter playlist name" :error-messages="errors.name" />
            <template v-if="editing">
              <p class="text-body-2 mb-4">Drag and drop to sort the position</p>
              <div class="d-flex mb-3" style="gap: 12px">
                <v-select v-model="addAssetId" :items="readyMedia" item-title="name" item-value="id" hide-details placeholder="Add media" />
                <v-btn variant="outlined" @click="addItem">Add</v-btn>
              </div>
              <draggable v-model="draftItems" item-key="media_asset_id" handle=".handle">
                <template #item="{ element, index }">
                  <v-card class="mb-3" variant="outlined">
                    <v-card-item>
                      <div class="d-flex align-center" style="gap: 8px">
                        <v-icon class="handle">mdi-drag</v-icon>
                        <span style="min-width: 140px">{{ element.name }}</span>
                        <v-chip size="small">{{ element.status }}</v-chip>
                        <v-text-field v-model.number="element.duration_ms" type="number" label="Duration ms" density="compact" hide-details />
                        <v-btn icon="mdi-close" size="small" variant="text" @click="draftItems.splice(index, 1)" />
                      </div>
                    </v-card-item>
                  </v-card>
                </template>
              </draggable>
            </template>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="outlined" @click="showModal = false">Cancel</v-btn>
            <v-btn variant="elevated" color="primary" @click="submit">{{ editing ? 'Save' : 'Create' }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </template>
  </AmsListPage>
</template>
