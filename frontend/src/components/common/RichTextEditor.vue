<script setup>
import { watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Underline from '@tiptap/extension-underline';
import Placeholder from '@tiptap/extension-placeholder';

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Start writing...'
    },
    error: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            codeBlock: {
                HTMLAttributes: {
                    class: 'rounded-md bg-surface-800 text-surface-0 p-4 overflow-x-auto my-4'
                }
            },
            blockquote: {
                HTMLAttributes: {
                    class: 'border-l-4 border-primary pl-4 italic text-surface-600 dark:text-surface-300 my-4'
                }
            },
            heading: {
                levels: [1, 2, 3]
            }
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-primary underline cursor-pointer'
            }
        }),
        Image.configure({
            HTMLAttributes: {
                class: 'max-w-full h-auto rounded-md my-4'
            }
        }),
        Underline,
        Placeholder.configure({
            placeholder: props.placeholder
        })
    ],
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    }
});

const setLink = () => {
    const previousUrl = editor.value?.getAttributes('link').href || '';
    const url = window.prompt('Enter URL', previousUrl);
    if (url === null) return;
    if (url === '') {
        editor.value?.chain().focus().unsetLink().run();
        return;
    }
    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};

const addImage = () => {
    const url = window.prompt('Enter image URL');
    if (url) {
        editor.value?.chain().focus().setImage({ src: url }).run();
    }
};

watch(
    () => props.modelValue,
    (newValue) => {
        const current = editor.value?.getHTML();
        if (newValue !== current) {
            editor.value?.commands.setContent(newValue || '', false);
        }
    }
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});
</script>

<template>
    <div class="border border-surface-300 dark:border-surface-600 rounded-lg overflow-hidden" :class="{ 'border-red-500': error }">
        <div v-if="editor" class="flex flex-wrap items-center gap-1 p-2 bg-surface-50 dark:bg-surface-800 border-b border-surface-300 dark:border-surface-600">
            <Button type="button" size="small" text severity="secondary" @click="editor.chain().focus().toggleBold().run()" :class="{ 'bg-surface-200 dark:bg-surface-700': editor.isActive('bold') }" icon="pi pi-bold" v-tooltip.top="'Bold'" />
            <Button type="button" size="small" text severity="secondary" @click="editor.chain().focus().toggleItalic().run()" :class="{ 'bg-surface-200 dark:bg-surface-700': editor.isActive('italic') }" icon="pi pi-italic" v-tooltip.top="'Italic'" />
            <Button
                type="button"
                size="small"
                text
                severity="secondary"
                @click="editor.chain().focus().toggleUnderline().run()"
                :class="{ 'bg-surface-200 dark:bg-surface-700': editor.isActive('underline') }"
                icon="pi pi-minus"
                v-tooltip.top="'Underline'"
            />
            <div class="w-px h-6 bg-surface-300 dark:bg-surface-600 mx-1"></div>
            <Button
                type="button"
                size="small"
                text
                severity="secondary"
                @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
                :class="{ 'bg-surface-200 dark:bg-surface-700': editor.isActive('heading', { level: 1 }) }"
                label="H1"
                v-tooltip.top="'Heading 1'"
            />
            <Button
                type="button"
                size="small"
                text
                severity="secondary"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                :class="{ 'bg-surface-200 dark:bg-surface-700': editor.isActive('heading', { level: 2 }) }"
                label="H2"
                v-tooltip.top="'Heading 2'"
            />
            <Button
                type="button"
                size="small"
                text
                severity="secondary"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                :class="{ 'bg-surface-200 dark:bg-surface-700': editor.isActive('heading', { level: 3 }) }"
                label="H3"
                v-tooltip.top="'Heading 3'"
            />
            <div class="w-px h-6 bg-surface-300 dark:bg-surface-600 mx-1"></div>
            <Button
                type="button"
                size="small"
                text
                severity="secondary"
                @click="editor.chain().focus().toggleBulletList().run()"
                :class="{ 'bg-surface-200 dark:bg-surface-700': editor.isActive('bulletList') }"
                icon="pi pi-list"
                v-tooltip.top="'Bullet List'"
            />
            <Button
                type="button"
                size="small"
                text
                severity="secondary"
                @click="editor.chain().focus().toggleOrderedList().run()"
                :class="{ 'bg-surface-200 dark:bg-surface-700': editor.isActive('orderedList') }"
                icon="pi pi-sort-numeric-down"
                v-tooltip.top="'Numbered List'"
            />
            <Button
                type="button"
                size="small"
                text
                severity="secondary"
                @click="editor.chain().focus().toggleBlockquote().run()"
                :class="{ 'bg-surface-200 dark:bg-surface-700': editor.isActive('blockquote') }"
                icon="pi pi-quote-left"
                v-tooltip.top="'Quote'"
            />
            <div class="w-px h-6 bg-surface-300 dark:bg-surface-600 mx-1"></div>
            <Button
                type="button"
                size="small"
                text
                severity="secondary"
                @click="editor.chain().focus().toggleCodeBlock().run()"
                :class="{ 'bg-surface-200 dark:bg-surface-700': editor.isActive('codeBlock') }"
                icon="pi pi-code"
                v-tooltip.top="'Code Block'"
            />
            <Button type="button" size="small" text severity="secondary" @click="setLink" icon="pi pi-link" v-tooltip.top="'Link'" />
            <Button type="button" size="small" text severity="secondary" @click="addImage" icon="pi pi-image" v-tooltip.top="'Image'" />
            <div class="w-px h-6 bg-surface-300 dark:bg-surface-600 mx-1"></div>
            <Button type="button" size="small" text severity="secondary" @click="editor.chain().focus().undo().run()" icon="pi pi-undo" v-tooltip.top="'Undo'" :disabled="!editor.can().undo()" />
            <Button type="button" size="small" text severity="secondary" @click="editor.chain().focus().redo().run()" icon="pi pi-reply" v-tooltip.top="'Redo'" :disabled="!editor.can().redo()" />
            <Button type="button" size="small" text severity="secondary" @click="editor.chain().focus().clearNodes().unsetAllMarks().run()" icon="pi pi-trash" v-tooltip.top="'Clear Formatting'" />
        </div>
        <EditorContent :editor="editor" class="min-h-[200px] max-h-[500px] overflow-y-auto p-4 bg-white dark:bg-surface-900 prose prose-sm dark:prose-invert max-w-none rich-text-content" />
    </div>
</template>

<style>
.rich-text-content .ProseMirror:focus {
    outline: none;
}

.rich-text-content .ProseMirror p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: #adb5bd;
    pointer-events: none;
    height: 0;
}

.rich-text-content h1 {
    font-size: 1.875rem;
    font-weight: 700;
    line-height: 2.25rem;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}

.rich-text-content h2 {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 2rem;
    margin-top: 1.25rem;
    margin-bottom: 0.75rem;
}

.rich-text-content h3 {
    font-size: 1.25rem;
    font-weight: 600;
    line-height: 1.75rem;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

.rich-text-content p {
    margin-bottom: 1rem;
}

.rich-text-content ul {
    list-style-type: disc;
    padding-left: 1.5rem;
    margin-bottom: 1rem;
}

.rich-text-content ol {
    list-style-type: decimal;
    padding-left: 1.5rem;
    margin-bottom: 1rem;
}

.rich-text-content li {
    margin-bottom: 0.25rem;
}

.rich-text-content code {
    background: var(--surface-200);
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.875em;
}

.dark .rich-text-content code {
    background: var(--surface-700);
}
</style>
