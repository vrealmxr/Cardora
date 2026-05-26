<script>
    (() => {
        const isVisible = (element) => {
            if (!element) {
                return false
            }

            const style = window.getComputedStyle(element)

            return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0'
        }

        const getAlpineData = (element) => {
            if (!element) {
                return null
            }

            if (Array.isArray(element._x_dataStack) && element._x_dataStack.length) {
                return element._x_dataStack[0]
            }

            return element.__x?.$data ?? null
        }

        const getVisibleModalWindow = (modalRoot) => {
            return Array.from(modalRoot.querySelectorAll('.fi-modal-window')).find((windowElement) => {
                return !windowElement.classList.contains('hidden') && isVisible(windowElement)
            })
        }

        const cleanupModalState = () => {
            let hasVisibleModal = false

            document.querySelectorAll('.fi-modal').forEach((modalRoot) => {
                const modalShell = Array.from(modalRoot.children).find((child) => child.matches?.('.fixed.inset-0.z-40'))
                const overlay = modalRoot.querySelector('.fi-modal-close-overlay')
                const visibleWindow = getVisibleModalWindow(modalRoot)
                const alpineData = getAlpineData(modalRoot)

                if (visibleWindow) {
                    hasVisibleModal = true
                    return
                }

                if (alpineData && Object.prototype.hasOwnProperty.call(alpineData, 'isOpen')) {
                    alpineData.isOpen = false
                }

                modalRoot.dispatchEvent(new CustomEvent('close-modal', { bubbles: true, detail: {} }))

                if (modalShell) {
                    modalShell.style.display = 'none'
                    modalShell.style.pointerEvents = 'none'
                }

                if (overlay) {
                    overlay.style.display = 'none'
                    overlay.style.pointerEvents = 'none'
                    overlay.style.opacity = '0'
                }
            })

            if (!hasVisibleModal) {
                document.documentElement.classList.remove('overflow-y-hidden')
                document.body.classList.remove('overflow-y-hidden')

                document.body.querySelectorAll('[inert]').forEach((element) => {
                    if (!element.closest('.fi-modal')) {
                        element.removeAttribute('inert')
                    }
                })

                if (document.activeElement instanceof HTMLElement && document.activeElement.closest('.fi-modal')) {
                    document.activeElement.blur()
                }
            }
        }

        const cleanupTableState = () => {
            document.querySelectorAll('.fi-ta').forEach((tableRoot) => {
                const alpineData = getAlpineData(tableRoot)

                if (!alpineData || !Object.prototype.hasOwnProperty.call(alpineData, 'selectedRecords')) {
                    return
                }

                const checkedRecords = Array.from(
                    tableRoot.querySelectorAll('.fi-ta-record-checkbox:checked'),
                ).map((checkbox) => String(checkbox.value))

                if (!checkedRecords.length && Array.isArray(alpineData.selectedRecords) && alpineData.selectedRecords.length) {
                    alpineData.selectedRecords = []

                    if (alpineData.$wire?.set) {
                        alpineData.$wire.set('selectedTableRecords', [], false)
                    }
                }

                if (Object.prototype.hasOwnProperty.call(alpineData, 'isLoading')) {
                    alpineData.isLoading = false
                }
            })
        }

        let cleanupTimer = null

        const runCleanup = () => {
            cleanupModalState()
            cleanupTableState()
        }

        const scheduleCleanup = () => {
            window.clearTimeout(cleanupTimer)
            cleanupTimer = window.setTimeout(runCleanup, 80)
        }

        document.addEventListener('DOMContentLoaded', scheduleCleanup)
        document.addEventListener('click', scheduleCleanup, true)
        document.addEventListener('keydown', scheduleCleanup, true)

        document.addEventListener('livewire:init', () => {
            scheduleCleanup()

            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                window.Livewire.hook('commit', ({ succeed, fail }) => {
                    succeed(() => scheduleCleanup())
                    fail(() => scheduleCleanup())
                })

                window.Livewire.hook('request', ({ respond, succeed, fail }) => {
                    respond(() => scheduleCleanup())
                    succeed(() => scheduleCleanup())
                    fail(() => scheduleCleanup())
                })
            }
        })

        const observer = new MutationObserver(scheduleCleanup)

        observer.observe(document.documentElement, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['class', 'style', 'inert'],
        })

        scheduleCleanup()
    })()
</script>
