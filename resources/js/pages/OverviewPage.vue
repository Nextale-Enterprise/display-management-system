<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useCommonStore } from '@/store/common'
import { useDeviceStore } from '@/store/device'
import { usePlaylistStore } from '@/store/playlist'

const common = useCommonStore()
const devices = useDeviceStore()
const playlists = usePlaylistStore()
const board = ref(null)
const positions = ref({})
let drag = null

const placed = computed(() => devices.getList.map((device, index) => {
  const saved = positions.value[device.id]
  const column = index % 3
  const row = Math.floor(index / 3)
  return {
    ...playing(device),
    x: saved?.x ?? 24 + column * 320,
    y: saved?.y ?? 24 + row * 250,
  }
}))

const canvasStyle = computed(() => {
  const width = Math.max(1100, ...placed.value.map(device => device.x + 320), 0)
  const height = Math.max(640, ...placed.value.map(device => device.y + 260), 0)
  return { width: `${width}px`, height: `${height}px` }
})

function playing(device) {
  const playlist = playlists.getList.find(item => item.id === device.playlist_id)
  const live = (playlist?.live_items || [])
    .filter(item => item.row_index === device.screen_row)
    .sort((a, b) => a.column_index - b.column_index)
  return {
    ...device,
    mediaRows: live.map(item => item.name),
    screen: live[0] || null,
  }
}

function storageKey() {
  return `overview-layout:${common.organizationSelected || 'none'}`
}

function loadPositions() {
  try {
    positions.value = JSON.parse(localStorage.getItem(storageKey()) || '{}')
  } catch {
    positions.value = {}
  }
}

function savePositions() {
  localStorage.setItem(storageKey(), JSON.stringify(positions.value))
}

function boardPoint(event) {
  const rect = board.value.getBoundingClientRect()
  return {
    x: event.clientX - rect.left + board.value.scrollLeft,
    y: event.clientY - rect.top + board.value.scrollTop,
  }
}

function onPointerDown(event, device) {
  if (event.button !== 0) return
  const point = boardPoint(event)
  drag = {
    id: device.id,
    dx: point.x - device.x,
    dy: point.y - device.y,
  }
  event.currentTarget.setPointerCapture(event.pointerId)
}

function onPointerMove(event, device) {
  if (!drag || drag.id !== device.id) return
  const point = boardPoint(event)
  positions.value = {
    ...positions.value,
    [device.id]: {
      x: Math.max(0, point.x - drag.dx),
      y: Math.max(0, point.y - drag.dy),
    },
  }
}

function onPointerUp() {
  if (!drag) return
  drag = null
  savePositions()
}

async function load() {
  loadPositions()
  await Promise.all([devices.refreshList(), playlists.refreshList()])
}

onMounted(load)
watch(() => common.organizationSelected, load)
</script>

<template>
  <div class="overview">
    <div class="overview__bar">
      <div>
        <h1 class="text-h5 mb-1">Overview</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Drag a TV anywhere on the board. The spot stays in this browser.
        </p>
      </div>
    </div>
    <div ref="board" class="board">
      <p v-if="!devices.getIsLoading && placed.length === 0" class="board__empty">
        No devices in this organization.
      </p>
      <div class="canvas" :style="canvasStyle">
        <div
          v-for="device in placed"
          :key="device.id"
          class="pair"
          :style="{ transform: `translate(${device.x}px, ${device.y}px)` }"
          @pointerdown="onPointerDown($event, device)"
          @pointermove="onPointerMove($event, device)"
          @pointerup="onPointerUp"
          @pointercancel="onPointerUp"
        >
          <div class="tv">
            <div class="tv__bezel">
              <div class="tv__screen" :class="{ 'tv__screen--media': device.screen?.url }">
                <video
                  v-if="device.screen?.type === 'video' && device.screen.url"
                  :src="device.screen.url"
                  muted
                  autoplay
                  loop
                  playsinline
                />
                <img
                  v-else-if="device.screen?.url"
                  :src="device.screen.url"
                  :alt="device.screen.name"
                  draggable="false"
                >
                <p v-else class="tv__title tv__title--empty">No media</p>
                <p class="tv__name">{{ device.name }}</p>
                <div class="tv__hover">
                  <p>{{ device.branch_name || 'No branch' }}</p>
                  <p v-if="device.is_head" class="assist__head">Head of this branch</p>
                  <p>Playlist: {{ device.playlist_name || 'None' }}</p>
                  <p v-if="device.mediaRows.length === 1">Playing this file only.</p>
                  <p v-else-if="device.mediaRows.length > 1">Playing the whole playlist, in order.</p>
                  <p v-else>Nothing is assigned yet.</p>
                  <ul v-if="device.mediaRows.length > 1">
                    <li v-for="row in device.mediaRows" :key="row">{{ row }}</li>
                  </ul>
                </div>
                <p v-if="device.mediaRows.length > 1" class="tv__count">1 / {{ device.mediaRows.length }}</p>
              </div>
            </div>
            <div class="tv__neck" />
            <div class="tv__foot" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overview__bar {
  margin-bottom: 16px;
}
.board {
  position: relative;
  min-height: 70vh;
  overflow: auto;
  background: #171717;
  border-radius: 12px;
}
.board__empty {
  position: absolute;
  z-index: 1;
  margin: 20px;
  color: #a3a3a3;
}
.canvas {
  position: relative;
}
.pair {
  position: absolute;
  top: 0;
  left: 0;
  width: 280px;
  padding: 8px;
  touch-action: none;
  cursor: grab;
  user-select: none;
}
.pair:active {
  cursor: grabbing;
  z-index: 2;
}
.tv {
  width: 280px;
  flex: 0 0 280px;
}
.tv__bezel {
  padding: 12px;
  background: #2a2a2a;
  border: 2px solid #4a4a4a;
  border-radius: 16px;
  box-shadow: inset 0 0 0 1px #111;
}
.tv__screen {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  aspect-ratio: 16 / 9;
  padding: 16px;
  overflow: hidden;
  background:
    radial-gradient(circle at 50% 40%, #243044, #0b0d10 72%);
  border-radius: 6px;
  text-align: center;
}
.tv__screen--media {
  padding: 0;
}
.tv__screen img,
.tv__screen video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.tv__title {
  margin: 0;
  color: #f8fafc;
  font-size: 22px;
  font-weight: 700;
  line-height: 1.25;
}
.tv__title--empty {
  color: #94a3b8;
  font-size: 16px;
  font-weight: 500;
}
.tv__count {
  position: absolute;
  right: 8px;
  bottom: 8px;
  z-index: 2;
  margin: 0;
  padding: 2px 6px;
  color: #fff;
  font-size: 12px;
  background: rgb(0 0 0 / 55%);
  border-radius: 6px;
}
.tv__name {
  position: absolute;
  top: 8px;
  left: 8px;
  z-index: 2;
  max-width: calc(100% - 16px);
  margin: 0;
  padding: 2px 8px;
  overflow: hidden;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
  background: rgb(0 0 0 / 55%);
  border-radius: 6px;
}
.tv__hover {
  position: absolute;
  inset: 0;
  z-index: 1;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 40px 12px 12px;
  color: #fff;
  font-size: 13px;
  text-align: left;
  background: rgb(0 0 0 / 62%);
  opacity: 0;
}
.pair:hover .tv__hover {
  opacity: 1;
}
.tv__hover p {
  margin: 0 0 4px;
}
.assist__head {
  color: #86efac;
}
.tv__hover ul {
  margin: 4px 0 0;
  padding-left: 18px;
}
.tv__neck {
  width: 36px;
  height: 14px;
  margin: 0 auto;
  background: #3a3a3a;
}
.tv__foot {
  width: 110px;
  height: 8px;
  margin: 0 auto;
  background: #4a4a4a;
  border-radius: 0 0 8px 8px;
}
</style>
