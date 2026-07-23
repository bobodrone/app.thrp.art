// Loads marked + DOMPurify into the global window so Alpine x-effect handlers
// in the Blade <x-markdown-editor> component can render live previews without
// needing a build-step per usage.
import { marked } from 'marked';
import DOMPurify from 'dompurify';

// Allow h1/h2/h3/del - matches the server-side Purifier 'markdown' config.
const SANITIZE_CONFIG = {
    ALLOWED_TAGS: [
        // Headings + paragraph + line breaks
        'p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        // Inline styles
        'b', 'strong', 'i', 'em', 'u', 'del', 's', 'strike', 'mark', 'code', 'span',
        // Lists
        'ul', 'ol', 'li',
        // Block elements
        'blockquote', 'pre', 'hr',
        // Links & images
        'a', 'img',
        // Tables
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        // Containers (for prose styling hooks)
        'div',
    ],
    ALLOWED_ATTR: ['href', 'title', 'target', 'rel', 'src', 'alt', 'width', 'height', 'class', 'style'],
    ALLOW_DATA_ATTR: false,
};

function renderMarkdown(md) {
    if (!md) return '';
    const raw = marked.parse(md, { async: false, breaks: true, gfm: true });
    return DOMPurify.sanitize(raw, SANITIZE_CONFIG);
}

function addRelAttr(node) {
    if (node.tagName === 'A' && node.getAttribute('target') === '_blank') {
        node.setAttribute('rel', 'noopener noreferrer');
    }
    return true;
}

DOMPurify.addHook('afterSanitizeAttributes', addRelAttr);

window.thrpMarkdown = renderMarkdown;