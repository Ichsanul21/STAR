import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';

document.querySelectorAll('[data-rich-text]').forEach((root) => {
    const element = root.querySelector('[data-editor]');
    const input = root.querySelector('input[type="hidden"]');
    if (!element || !input) return;

    const editor = new Editor({
        element,
        extensions: [
            StarterKit,
            Link.configure({ openOnClick: false, autolink: true }),
            Placeholder.configure({ placeholder: 'Tulis narasi laporan…' }),
        ],
        content: element.innerHTML,
        onUpdate: ({ editor: instance }) => {
            input.value = instance.getHTML();
        },
    });

    root.querySelectorAll('[data-action]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.action;
            const chain = editor.chain().focus();

            switch (action) {
                case 'bold': chain.toggleBold().run(); break;
                case 'italic': chain.toggleItalic().run(); break;
                case 'strike': chain.toggleStrike().run(); break;
                case 'h2': chain.toggleHeading({ level: 2 }).run(); break;
                case 'h3': chain.toggleHeading({ level: 3 }).run(); break;
                case 'bullet': chain.toggleBulletList().run(); break;
                case 'ordered': chain.toggleOrderedList().run(); break;
                case 'quote': chain.toggleBlockquote().run(); break;
                case 'undo': chain.undo().run(); break;
                case 'redo': chain.redo().run(); break;
                case 'link': {
                    const url = window.prompt('URL link:');
                    if (url) {
                        chain.extendMarkRange('link').setLink({ href: url }).run();
                    } else {
                        chain.unsetLink().run();
                    }
                    break;
                }
            }
        });
    });
});
