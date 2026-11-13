<script setup>
/** Publicación del Classroom (aviso o material) con archivos, enlaces/videos y comentarios de la clase. */
import { NotificationOutlined, FolderOpenOutlined, PushpinFilled, PushpinOutlined, EditOutlined, DeleteOutlined, LinkOutlined, PlayCircleFilled, GoogleOutlined, MoreOutlined } from '@ant-design/icons-vue';
import FileChips from '@/Components/FileChips.vue';
import CommentThread from '@/Components/Classroom/CommentThread.vue';

defineProps({ post: { type: Object, required: true }, canComment: { type: Boolean, default: true } });
const emit = defineEmits(['edit', 'remove', 'pin']);
</script>

<template>
    <article class="pc" :class="[`is-${post.type}`, { pinned: post.pinned }]">
        <header class="pc__head">
            <span class="pc__ic"><FolderOpenOutlined v-if="post.type === 'material'" /><NotificationOutlined v-else /></span>
            <div class="pc__who">
                <b>{{ post.author }}<template v-if="post.type === 'material'"> publicó material</template><template v-else> publicó un aviso</template></b>
                <small>{{ post.at }}<template v-if="post.edited"> · editado</template></small>
            </div>
            <span v-if="post.pinned" class="pc__pin"><PushpinFilled /> Fijado</span>
            <a-dropdown v-if="post.can_edit" :trigger="['click']" placement="bottomRight">
                <button type="button" class="pc__more"><MoreOutlined /></button>
                <template #overlay>
                    <a-menu>
                        <a-menu-item key="pin" @click="emit('pin', post)"><PushpinOutlined /> {{ post.pinned ? 'Desfijar' : 'Fijar arriba' }}</a-menu-item>
                        <a-menu-item key="edit" @click="emit('edit', post)"><EditOutlined /> Editar</a-menu-item>
                        <a-menu-item key="del" danger @click="emit('remove', post)"><DeleteOutlined /> Eliminar</a-menu-item>
                    </a-menu>
                </template>
            </a-dropdown>
        </header>

        <h3 v-if="post.title" class="pc__title">{{ post.title }}</h3>
        <p v-if="post.body" class="pc__body">{{ post.body }}</p>

        <div v-if="post.links.length" class="pc__links">
            <a v-for="l in post.links" :key="l.url" :href="l.url" target="_blank" rel="noopener" class="pc__link" :class="`is-${l.kind}`">
                <span class="pc__thumb">
                    <img v-if="l.thumb" :src="l.thumb" alt="" loading="lazy" />
                    <GoogleOutlined v-else-if="l.kind === 'drive'" />
                    <LinkOutlined v-else />
                    <PlayCircleFilled v-if="l.kind === 'video'" class="pc__play" />
                </span>
                <span class="pc__lt"><b>{{ l.title }}</b><small>{{ l.kind === 'video' ? 'Video · YouTube' : l.host }}</small></span>
            </a>
        </div>
        <FileChips v-if="post.files.length" :files="post.files" :title="post.title || 'Material de la clase'" subtitle="Material de la clase" class="pc__files" />

        <div class="pc__comments">
            <CommentThread :items="post.comments" type="post" :id="post.id" :can-write="canComment" compact />
        </div>
    </article>
</template>

<style scoped>
.pc { background: #fff; border: 1px solid #e1e8f0; border-radius: 16px; padding: 14px 16px 12px; display: flex; flex-direction: column; gap: 10px; transition: box-shadow .15s; }
.pc:hover { box-shadow: 0 10px 24px -20px rgba(10, 31, 68, .45); }
.pc.pinned { border-color: #f5c26b; box-shadow: 0 0 0 2px #fff3dc; }
.pc__head { display: flex; align-items: center; gap: 10px; }
.pc__ic { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 17px; flex: none; background: linear-gradient(135deg, #1d7fc9, #14a39a); }
.pc.is-material .pc__ic { background: linear-gradient(135deg, #f39200, #e0457b); }
.pc__who { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.25; }
.pc__who b { font-size: 13.5px; color: #0a1f44; }
.pc__who small { font-size: 12px; color: #94a3b8; }
.pc__pin { font-size: 11.5px; font-weight: 700; color: #b45309; display: inline-flex; gap: 4px; align-items: center; }
.pc__more { border: 0; background: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; color: #5d7085; font-size: 16px; }
.pc__more:hover { background: #f1f5f9; }
.pc__title { margin: 0; font-size: 16px; font-weight: 800; color: #0a1f44; }
.pc__body { margin: 0; font-size: 14px; color: #334155; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.5; }
.pc__links { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 8px; }
.pc__link { display: flex; align-items: center; gap: 10px; border: 1px solid #e1e8f0; border-radius: 12px; overflow: hidden; color: inherit; transition: border-color .15s; min-width: 0; }
.pc__link:hover { border-color: #1d7fc9; }
.pc__thumb { position: relative; width: 96px; height: 60px; flex: none; display: grid; place-items: center; background: #eef2f7; color: #5d7085; font-size: 22px; }
.pc__thumb img { width: 100%; height: 100%; object-fit: cover; }
.pc__play { position: absolute; font-size: 26px; color: #fff; filter: drop-shadow(0 2px 6px rgba(0, 0, 0, .5)); }
.pc__link.is-drive .pc__thumb { color: #1d8a4e; background: #e3f8ec; }
.pc__lt { min-width: 0; display: flex; flex-direction: column; padding-right: 10px; }
.pc__lt b { font-size: 13px; color: #0a1f44; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pc__lt small { font-size: 11.5px; color: #94a3b8; }
.pc__files { display: flex; }
.pc__comments { border-top: 1px solid #eef2f7; padding-top: 10px; }
</style>
