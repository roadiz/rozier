import $ from 'jquery'

export default class AttributeValuePosition {
    constructor() {
        this.$list = $('.attribute-value-forms > .uk-sortable')
        this.currentRequest = null

        // Bind methods
        this.onSortableChange = this.onSortableChange.bind(this)

        this.init()
    }

    init() {
        if (this.$list.length && this.$list.children().length > 1) {
            this.$list.on('change.uk.sortable', this.onSortableChange)
        }
    }

    unbind() {
        if (this.$list.length && this.$list.children().length > 1) {
            this.$list.off('change.uk.sortable', this.onSortableChange)
        }
    }

    /**
     * @param event
     * @param list
     * @param element
     */
    async onSortableChange(event, list, element) {
        if (this.currentRequest && this.currentRequest.readyState !== 4) {
            this.currentRequest.abort()
        }

        if (event.target instanceof HTMLInputElement) {
            return
        }

        let $element = $(element)
        let attributeValueId = parseInt($element.data('id'))
        let $sibling = $element.prev()
        let beforeElementId = null
        let afterElementId = null

        if ($sibling.length === 0) {
            $sibling = $element.next()
            beforeElementId = parseInt($sibling.data('id'))
        } else {
            afterElementId = parseInt($sibling.data('id'))
        }

        try {
            const response = await fetch(route.replace('%attributeValueId%', attributeValueId), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                },
                body: new URLSearchParams({
                    _token: window.Rozier.ajaxToken,
                    _action: 'updatePosition',
                    attributeValueId: attributeValueId,
                    beforeAttributeValueId: beforeElementId,
                    afterAttributeValueId: afterElementId,
                }),
            })
            if (!response.ok) {
                throw response
            }
            const data = await response.json()
            $element.attr('data-position', newPosition)
            window.UIkit.notify({
                message: data.responseText,
                status: data.status,
                timeout: 3000,
                pos: 'top-center',
            })
        } catch (response) {
            const data = await response.json()
            window.UIkit.notify({
                message: data.title || '',
                status: 'danger',
                timeout: 3000,
                pos: 'top-center',
            })
                .done((data) => {
                    $element.attr('data-position', newPosition)
                    window.UIkit.notify({
                        message: data.responseText,
                        status: data.status,
                        timeout: 3000,
                        pos: 'top-center',
                    })
                })
                .fail((data) => {
                    data = JSON.parse(data.responseText)
                    window.UIkit.notify({
                        message: data.error_message,
                        status: 'danger',
                        timeout: 3000,
                        pos: 'top-center',
                    })
                })
        }
    }
}
