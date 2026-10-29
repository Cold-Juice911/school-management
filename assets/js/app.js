'use strict';

const confirmationDialog = document.querySelector('#action-confirmation');

if (confirmationDialog instanceof HTMLDialogElement) {
    const confirmationTitle = confirmationDialog.querySelector('#action-confirmation-title');
    const confirmationMessage = confirmationDialog.querySelector('#action-confirmation-message');
    const cancelButton = confirmationDialog.querySelector('[data-confirmation-cancel]');
    const confirmButton = confirmationDialog.querySelector('[data-confirmation-submit]');
    let pendingForm = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
        if (form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;
            return;
        }

        event.preventDefault();
        if (confirmationDialog.open) return;
        if (!(confirmationTitle instanceof HTMLElement) || !(confirmationMessage instanceof HTMLElement) || !(confirmButton instanceof HTMLButtonElement) || typeof confirmationDialog.showModal !== 'function') return;

        const variant = ['success', 'warning', 'danger'].includes(form.dataset.confirmVariant ?? '') ? form.dataset.confirmVariant : 'danger';
        confirmationTitle.textContent = form.dataset.confirmTitle ?? 'Confirm action';
        confirmationMessage.textContent = form.dataset.confirm ?? 'Continue with this action?';
        confirmButton.textContent = form.dataset.confirmLabel ?? 'Confirm';
        confirmButton.className = `btn btn-${variant}`;
        pendingForm = form;
        confirmationDialog.showModal();
    });

    if (cancelButton instanceof HTMLButtonElement) {
        cancelButton.addEventListener('click', () => {
            pendingForm = null;
            confirmationDialog.close('cancel');
        });
    }

    if (confirmButton instanceof HTMLButtonElement) {
        confirmButton.addEventListener('click', () => {
            const form = pendingForm;
            pendingForm = null;
            confirmationDialog.close('confirm');
            if (form === null) return;
            form.dataset.confirmed = 'true';
            form.requestSubmit();
        });
    }

    confirmationDialog.addEventListener('click', (event) => {
        if (event.target === confirmationDialog) {
            pendingForm = null;
            confirmationDialog.close('cancel');
        }
    });

    confirmationDialog.addEventListener('close', () => {
        pendingForm = null;
    });
}

document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const row = target.closest('tr[data-row-href]');
    if (!(row instanceof HTMLTableRowElement) || target.closest('a, button, form, input, select, textarea, label')) return;
    const href = row.dataset.rowHref;
    if (href) window.location.assign(href);
});

for (const table of document.querySelectorAll('.table-scroll table')) {
    if (!(table instanceof HTMLTableElement) || table.tHead === null || table.tBodies.length === 0) continue;

    const rows = Array.from(table.tBodies).flatMap((body) => Array.from(body.rows));
    const tableContainer = table.closest('.table-scroll');
    if (!(tableContainer instanceof HTMLElement)) continue;

    let currentPage = 1;
    let pageSize = 10;
    const pageSizes = [10, 20, 50];
    const totalPages = () => Math.max(1, Math.ceil(rows.length / pageSize));

    const toolbar = document.createElement('div');
    toolbar.className = 'table-pagination-toolbar';
    const sizeLabel = document.createElement('label');
    sizeLabel.className = 'table-page-size-label';
    sizeLabel.append(document.createTextNode('Rows per page'));
    const sizeSelect = document.createElement('select');
    sizeSelect.className = 'table-page-size-select';
    sizeSelect.setAttribute('aria-label', 'Rows per page');
    for (const size of pageSizes) {
        const option = document.createElement('option');
        option.value = String(size);
        option.textContent = String(size);
        sizeSelect.append(option);
    }
    sizeLabel.append(sizeSelect);
    toolbar.append(sizeLabel);
    const contentCard = tableContainer.closest('.content-card');
    const filterForm = contentCard?.querySelector('.filter-form');
    if (filterForm instanceof HTMLFormElement) {
        filterForm.append(toolbar);
    } else {
        tableContainer.before(toolbar);
    }

    const pagination = document.createElement('nav');
    pagination.className = 'table-pagination-footer';
    pagination.setAttribute('aria-label', 'Table pagination');
    const previousControls = document.createElement('div');
    previousControls.className = 'table-pagination-controls';
    const pageLabel = document.createElement('span');
    pageLabel.className = 'table-pagination-status';
    pageLabel.setAttribute('aria-live', 'polite');
    const nextControls = document.createElement('div');
    nextControls.className = 'table-pagination-controls table-pagination-controls-next';

    const createPageButton = (symbol, accessibleName, pageForAction, group) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-secondary table-page-button';
        button.textContent = symbol;
        button.setAttribute('aria-label', accessibleName);
        button.addEventListener('click', () => {
            currentPage = pageForAction();
            renderPage();
        });
        group.append(button);
        return button;
    };

    let firstPageButton;
    let previousPageButton;
    let nextPageButton;
    let lastPageButton;

    firstPageButton = createPageButton('«', 'First page', () => 1, previousControls);
    previousPageButton = createPageButton('‹', 'Previous page', () => Math.max(1, currentPage - 1), previousControls);
    nextPageButton = createPageButton('›', 'Next page', () => Math.min(totalPages(), currentPage + 1), nextControls);
    lastPageButton = createPageButton('»', 'Last page', () => totalPages(), nextControls);
    pagination.append(previousControls, pageLabel, nextControls);
    tableContainer.after(pagination);

    function renderPage() {
        const lastPage = totalPages();
        currentPage = Math.min(Math.max(1, currentPage), lastPage);
        const firstVisibleRow = (currentPage - 1) * pageSize;
        const lastVisibleRow = firstVisibleRow + pageSize;
        rows.forEach((row, index) => {
            row.hidden = index < firstVisibleRow || index >= lastVisibleRow;
        });
        pageLabel.textContent = `Page ${currentPage} of ${lastPage}`;
        firstPageButton.disabled = currentPage === 1;
        previousPageButton.disabled = currentPage === 1;
        nextPageButton.disabled = currentPage === lastPage;
        lastPageButton.disabled = currentPage === lastPage;
    }

    sizeSelect.addEventListener('change', () => {
        const requestedSize = Number(sizeSelect.value);
        pageSize = pageSizes.includes(requestedSize) ? requestedSize : 10;
        currentPage = 1;
        renderPage();
    });

    renderPage();
}

for (const notice of document.querySelectorAll('.notice')) {
    window.setTimeout(() => {
        notice.classList.add('is-hiding');
        window.setTimeout(() => notice.remove(), 400);
    }, 5000);
}

document.addEventListener('click', (event) => {
    const target = event.target;
    if (target instanceof Element && target.closest('[data-print]')) window.print();
});
