import jspreadsheet from 'jspreadsheet-ce';
import 'jspreadsheet-ce/dist/jspreadsheet.css';
import 'jsuites/dist/jsuites.css';

function parseRosterCell(raw, types) {
    const trimmed = String(raw ?? '').trim();
    if (trimmed === '') {
        return { kind: 'empty' };
    }

    if (trimmed.includes('-')) {
        const match = trimmed.match(/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/);
        if (!match) {
            return { kind: 'invalid' };
        }
        const startHour = Number(match[1]);
        const startMinute = Number(match[2]);
        const endHour = Number(match[3]);
        const endMinute = Number(match[4]);
        if (startHour > 23 || endHour > 23 || startMinute > 59 || endMinute > 59) {
            return { kind: 'invalid' };
        }
        if (endHour * 60 + endMinute <= startHour * 60 + startMinute) {
            return { kind: 'invalid' };
        }

        return { kind: 'free', color: 'none' };
    }

    const code = trimmed.toUpperCase();
    const type = types.find((item) => item.active && item.code === code);
    if (!type) {
        return { kind: 'invalid' };
    }

    return { kind: 'type', color: type.color, display: type.code };
}

function applyCellClasses(worksheet, payload) {
    if (!worksheet || typeof worksheet.getData !== 'function') {
        return;
    }
    const types = payload.types ?? [];
    const rows = worksheet.getData() ?? [];
    rows.forEach((row, rowIndex) => {
        row.forEach((value, colIndex) => {
            if (colIndex === 0) {
                return;
            }
            const cell = typeof worksheet.getCellFromCoords === 'function'
                ? worksheet.getCellFromCoords(colIndex, rowIndex)
                : null;
            if (!cell) {
                return;
            }
            [...cell.classList].forEach((name) => {
                if (name.startsWith('wp-roster-cell--')) {
                    cell.classList.remove(name);
                }
            });
            const parsed = parseRosterCell(value, types);
            if (parsed.kind === 'invalid') {
                cell.classList.add('wp-roster-cell--invalid');
                cell.title = payload.invalid_message || '';
                cell.setAttribute('aria-invalid', 'true');
            } else if (parsed.color && parsed.color !== 'none') {
                cell.classList.add(`wp-roster-cell--${parsed.color}`);
                cell.removeAttribute('title');
                cell.removeAttribute('aria-invalid');
            } else {
                cell.removeAttribute('title');
                cell.removeAttribute('aria-invalid');
            }
        });
    });
}

function collectCells(worksheet, payload) {
    const cells = [];
    payload.workers.forEach((worker, rowIndex) => {
        payload.dates.forEach((date, dayIndex) => {
            const raw = worksheet.getValueFromCoords(dayIndex + 1, rowIndex);
            cells.push({
                worker_id: worker.id,
                date,
                raw: raw == null ? '' : String(raw),
            });
        });
    });

    return cells;
}

function hasInvalidCells(worksheet, payload) {
    return collectCells(worksheet, payload).some(
        (cell) => parseRosterCell(cell.raw, payload.types ?? []).kind === 'invalid',
    );
}

function buildData(payload) {
    return payload.workers.map((worker) => {
        const row = [worker.name];
        payload.dates.forEach((date) => {
            const key = `${worker.id}:${date}`;
            row.push(payload.cells[key]?.display ?? '');
        });

        return row;
    });
}

export function bind(root, wire) {
    if (root.dataset.wpRosterBound === '1') {
        return;
    }
    root.dataset.wpRosterBound = '1';

    const grid = root.querySelector('[data-wp-roster-grid]');
    const banner = root.querySelector('[data-wp-roster-banner]');
    if (!grid) {
        return;
    }

    let worksheet = null;
    let payload = null;

    const showError = (message) => {
        if (!banner) {
            return;
        }
        banner.hidden = !message;
        banner.textContent = message || '';
    };

    const destroy = () => {
        if (worksheet) {
            jspreadsheet.destroy(grid, true);
            worksheet = null;
        }
        grid.querySelectorAll('.jss').forEach((node) => node.remove());
    };

    const mount = async () => {
        destroy();
        payload = await wire.payload();
        const isMonth = payload.period === 'month';
        const dayCount = payload.dates.length;
        grid.classList.toggle('wp-roster-sheet--month', isMonth);
        const columns = [
            {
                type: 'text',
                title: '',
                width: isMonth ? 128 : 180,
                readOnly: true,
            },
            ...payload.dates.map((_date, index) => ({
                type: 'text',
                title: isMonth
                    ? `${payload.day_numbers[index]}\n${payload.day_labels[index]}`
                    : payload.day_labels[index],
                width: isMonth ? 40 : 110,
            })),
        ];

        const worksheetConfig = {
            data: buildData(payload),
            columns,
            freezeColumns: 1,
            tableOverflow: true,
            tableWidth: '100%',
            tableHeight: '480px',
            allowInsertRow: false,
            allowManualInsertRow: false,
            allowInsertColumn: false,
            allowManualInsertColumn: false,
            allowDeleteRow: false,
            allowDeleteColumn: false,
            columnDrag: false,
            rowDrag: false,
            parseFormulas: false,
            minDimensions: [dayCount + 1, Math.max(payload.workers.length, 1)],
        };
        if (isMonth && payload.month_label) {
            worksheetConfig.nestedHeaders = [[
                { title: '', colspan: 1 },
                { title: payload.month_label, colspan: dayCount },
            ]];
        }

        const instances = jspreadsheet(grid, {
            worksheets: [worksheetConfig],
            contextMenu: () => false,
            onbeforechange: (_instance, _cell, x, _y, value) => {
                if (Number(x) === 0) {
                    return false;
                }
                if (typeof value === 'string' && value.trim().startsWith('=')) {
                    return false;
                }
                if (typeof value === 'string' && !value.includes('-')) {
                    return value.trim().toUpperCase();
                }

                return value;
            },
            onbeforepaste: (_instance, data) => {
                if (typeof data === 'string') {
                    return data
                        .split('\n')
                        .map((line) => line.split('\t').slice(0, dayCount).join('\t'))
                        .join('\n');
                }

                return data;
            },
            onafterchanges: (instance) => {
                applyCellClasses(instance, payload);
            },
            onload: (instance) => {
                applyCellClasses(instance, payload);
            },
        });

        worksheet = Array.isArray(instances) ? instances[0] : instances;
        applyCellClasses(worksheet, payload);
        if (isMonth) {
            requestAnimationFrame(() => fitMonthColumns(worksheet, grid, dayCount));
        }
    };

    const fitMonthColumns = (sheet, host, dayCount) => {
        if (!sheet || typeof sheet.setWidth !== 'function' || dayCount < 1) {
            return;
        }
        const nameWidth = 132;
        const total = Math.floor(host.clientWidth);
        const dayWidth = Math.max(36, Math.floor((total - nameWidth) / dayCount));
        sheet.setWidth(0, nameWidth);
        const dayIndexes = [];
        const dayWidths = [];
        for (let i = 1; i <= dayCount; i += 1) {
            dayIndexes.push(i);
            dayWidths.push(dayWidth);
        }
        sheet.setWidth(dayIndexes, dayWidths);
    };

    if (typeof ResizeObserver === 'function') {
        new ResizeObserver(() => {
            if (payload?.period === 'month' && worksheet) {
                fitMonthColumns(worksheet, grid, payload.dates.length);
            }
        }).observe(grid);
    }

    root.querySelector('[data-wp-roster-save]')?.addEventListener('click', async () => {
        if (!worksheet || !payload) {
            return;
        }
        if (hasInvalidCells(worksheet, payload)) {
            applyCellClasses(worksheet, payload);
            showError(payload.invalid_message || '');
            return;
        }
        showError('');
        await wire.save(collectCells(worksheet, payload));
    });

    root.querySelector('[data-wp-roster-copy]')?.addEventListener('click', async () => {
        await wire.copyToNextWeek();
    });

    root.querySelector('[data-wp-roster-publish]')?.addEventListener('click', async () => {
        const confirmMessage = root.querySelector('[data-wp-roster-publish]')?.getAttribute('data-confirm');
        if (confirmMessage && !window.confirm(confirmMessage)) {
            return;
        }
        await wire.publish();
    });

    if (typeof wire.on === 'function') {
        wire.on('roster-week-changed', () => {
            showError('');
            mount();
        });
        wire.on('roster-save-failed', (event) => {
            showError(event.message || '');
            applyCellClasses(worksheet, payload);
        });
    }

    mount();
}

window.wpRosterSheet = { bind };
