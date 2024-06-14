import $ from 'jquery'

/**
 * Custom form fields position
 */
export default class CustomFormFieldsPosition {
    constructor() {
        this.$list = $('.custom-form-fields > .uk-sortable')
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

    async onSortableChange(event, list, element) {
        let $element = $(element)
        let customFormFieldId = parseInt($element.data('field-id'))
        let $sibling = $element.prev()
        let afterFieldId = null
        let beforeFieldId = null

        if ($sibling.length === 0) {
            $sibling = $element.next()
            beforeFieldId = parseInt($sibling.data('field-id'))
        } else {
            afterFieldId = parseInt($sibling.data('field-id'))
        }

        const postData = {
            _token: window.Rozier.ajaxToken,
            _action: 'updatePosition',
            customFormFieldId: customFormFieldId,
            beforeFieldId: beforeFieldId,
            afterFieldId: afterFieldId,
        }
        const response = await fetch(
            window.Rozier.routes.customFormsFieldAjaxEdit.replace('%customFormFieldId%', customFormFieldId),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                },
                body: new URLSearchParams(postData),
            }
        )
        if (!response.ok) {
            const data = await response.json()
            window.UIkit.notify({
                message: data.title,
                status: 'danger',
                timeout: 3000,
                pos: 'top-center',
            })
        } else {
            const data = await response.json()
            window.UIkit.notify({
                message: data.responseText,
                status: data.status,
                timeout: 3000,
                pos: 'top-center',
            })
        }
    }
}
