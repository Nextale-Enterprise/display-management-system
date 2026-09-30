<script setup>
import { computed, onMounted, ref } from 'vue'
import { useField, useForm } from 'vee-validate'
import { useToast } from 'vue-toastification'
import draggable from 'vuedraggable'
import * as yup from 'yup'
import { useMediaStore } from '@/store/media'
import { usePlaylistStore } from '@/store/playlist'
import { confirm } from '@/plugins/confirm'
import { errorText } from '@/utils/feedback'
import AmsListPage from '@/components/AmsListPage.vue'
import AmsDataTable from '@/components/AmsDataTable.vue'

const store = usePlaylistStore()
const media = useMediaStore()
const toast = useToast()
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
  const wasEditing = Boolean(editing.value)
  try {
    if (!editing.value) {
      await store.create({ name: values.name })
      showModal.value = false
      toast.success('Playlist created')
      return
    }
    await store.update({ id: editing.value.id, name: values.name })
    await store.saveItems(editing.value.id, draftItems.value.map(item => ({
      media_asset_id: item.media_asset_id,
      duration_ms: Number(item.duration_ms),
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
        { title: 'Published', key: 'published_generation' },
      ]"
    >
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
      <VDialog v-model="showModal" max-width="720">
        <VCard>
          <VCardTitle class="text-h5 pt-6 px-6">{{ editing ? 'Edit Playlist' : 'Add Playlist' }}</VCardTitle>
          <VCardText>
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-3" :text="formError" />
            <label>Name:</label>
            <VTextField v-model="name" placeholder="Enter playlist name" :error-messages="errors.name" />
            <template v-if="editing">
              <p class="text-body-2 mb-4">Drag and drop to sort the position</p>
              <div class="d-flex mb-3" style="gap: 12px">
                <VSelect v-model="addAssetId" :items="readyMedia" item-title="name" item-value="id" hide-details placeholder="Add media" />
                <VBtn variant="outlined" @click="addItem">Add</VBtn>
              </div>
              <draggable v-model="draftItems" item-key="media_asset_id" handle=".handle">
                <template #item="{ element, index }">
                  <VCard class="mb-3" variant="outlined">
                    <VCardItem>
                      <div class="d-flex align-center" style="gap: 8px">
                        <VIcon class="handle" icon="tabler-grip-vertical" />
                        <span style="min-width: 140px">{{ element.name }}</span>
                        <VChip size="small" variant="tonal">{{ element.status }}</VChip>
                        <VTextField v-model.number="element.duration_ms" type="number" label="Duration ms" density="compact" hide-details />
                        <IconBtn @click="draftItems.splice(index, 1)">
                          <VIcon icon="tabler-x" />
                        </IconBtn>
                      </div>
                    </VCardItem>
                  </VCard>
                </template>
              </draggable>
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
