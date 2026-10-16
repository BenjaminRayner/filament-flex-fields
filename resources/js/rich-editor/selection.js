export function isImageNodeSelected(editor) {
    const { selection } = editor.state

    if (selection?.node?.type?.name === 'image') {
        return true
    }

    return editor.isActive('image')
}

export function getSelectedImageAnchor(editor, content) {
    if (! editor || ! content) {
        return null
    }

    const { selection } = editor.state

    if (selection?.node?.type?.name === 'image') {
        const nodeDom = editor.view.nodeDOM(selection.from)

        if (nodeDom instanceof HTMLElement) {
            if (nodeDom.tagName === 'IMG') {
                return nodeDom
            }

            const nestedImage = nodeDom.querySelector('img')

            if (nestedImage instanceof HTMLImageElement) {
                return nestedImage
            }

            return nodeDom
        }
    }

    const selectedNode = content.querySelector('.ProseMirror-selectednode')

    if (selectedNode instanceof HTMLElement) {
        if (selectedNode.tagName === 'IMG') {
            return selectedNode
        }

        const nestedImage = selectedNode.querySelector('img')

        if (nestedImage instanceof HTMLImageElement) {
            return nestedImage
        }

        return selectedNode
    }

    const selectedImage = content.querySelector('img.ProseMirror-selectednode')

    if (selectedImage instanceof HTMLImageElement) {
        return selectedImage
    }

    return null
}

export function shouldHideParagraphFloatingToolbar(editor) {
    return isImageNodeSelected(editor)
}

