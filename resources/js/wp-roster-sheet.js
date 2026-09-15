import jspreadsheet from 'jspreadsheet-ce';
import 'jspreadsheet-ce/dist/jspreadsheet.css';
import 'jsuites/dist/jsuites.css';

function cellText(raw) {
    if (raw == null) {
        return '';
    }
    if (typeof raw === 'object') {
        if (raw.value != null && typeof raw.value !== 'object') {
            return String(raw.value);
        }
        if (typeof raw.display === 'string') {
            return raw.display;
        }

        return '';
    }

    return String(raw);
}

function pasteGrid(data) {
    if (typeof data === 'string') {
        return data.replace(/\r\n|\r/g, '\n').split('\n').map((line) => line.split('\t'));
    }
    if (!Array.isArray(data)) {
        return [];
    }

    return data.map((row) => {
        if (typeof row === 'string') {
            return row.split('\t');
        }
        if (!Array.isArray(row)) {
            return [cellText(row)];
        }

        return row.map(cellText);
    });
}

function splitDutyAndUnit(raw) {
    if (raw.includes('/')) {
        const parts = raw.split('/');
        if (parts.length !== 2) {
            return null;
        }
        const duty = parts[0].trim();
        const unit = parts[1].trim();
        if (duty === '' || unit === '' || unit.includes('/')) {
            return null;
        }

        return [duty, unit];
    }

    const timeMatch = raw.match(/^(\d{1,2}:\d{2}\s*-\s*\d{1,2}:\d{2})(?:\s+(\S+))?$/);
    if (timeMatch) {
        return [timeMatch[1], timeMatch[2] || null];
    }

    const two = raw.match(/^(\S+)\s+(\S+)$/);
    if (two) {
        return [two[1], two[2]];
    }

    return [raw, null];
}

function parseDuty(duty, types) {
    if (duty.includes('-')) {
        const match = duty.match(/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/);
        if (!match) {
            return { kind: 'invalid', error: 'time.schedule.errors.invalid_time' };
        }
        const startHour = Number(match[1]);
        const startMinute = Number(match[2]);
        const endHour = Number(match[3]);
        const endMinute = Number(match[4]);
        if (startHour > 23 || endHour > 23 || startMinute > 59 || endMinute > 59) {
            return { kind: 'invalid', error: 'time.schedule.errors.invalid_time' };
        }
        if (endHour * 60 + endMinute <= startHour * 60 + startMinute) {
            return { kind: 'invalid', error: 'time.schedule.errors.night_not_allowed' };
        }

        return { kind: 'free', color: 'none' };
    }

    const code = duty.trim().toUpperCase();
    const type = types.find((item) => item.active && item.code === code);
    if (!type) {
        return { kind: 'invalid', error: 'time.schedule.errors.unknown_code' };
    }

    return {
        kind: type.kind && type.kind !== 'work' ? 'absence' : 'type',
        color: type.color,
        display: type.code,
        absence: Boolean(type.kind && type.kind !== 'work'),
    };
}

function catalogForWorker(worker, units) {
    const all = Array.isArray(units) ? units : [];
    if (!worker || worker.clocks_all_locations) {
        return all;
    }
    const ids = worker.location_ids ?? [];
    if (ids.length === 0) {
        return all;
    }

    return all.filter((unit) => ids.includes(unit.location_id));
}

function parseRosterCell(raw, types, units) {
    const trimmed = cellText(raw).trim();
    if (trimmed === '') {
        return { kind: 'empty' };
    }

    const split = splitDutyAndUnit(trimmed);
    if (!split) {
        return { kind: 'invalid', error: 'time.schedule.errors.unknown_code' };
    }

    const [duty, unitToken] = split;
    const parsed = parseDuty(duty, types);
    if (parsed.kind === 'invalid' || !unitToken) {
        return parsed;
    }

    if (parsed.absence || parsed.kind === 'absence') {
        return { kind: 'invalid', error: 'time.schedule.errors.absence_has_unit' };
    }

    const code = unitToken.trim().toUpperCase();
    if (code === '' || code.length > 8 || !/^[A-Z0-9]+$/.test(code)) {
        return { kind: 'invalid', error: 'time.schedule.errors.unknown_unit' };
    }

    const matches = (Array.isArray(units) ? units : []).filter(
        (unit) => String(unit.code || '').toUpperCase() === code,
    );
    if (matches.length === 0) {
        return { kind: 'invalid', error: 'time.schedule.errors.unknown_unit' };
    }
    if (matches.length > 1) {
        return { kind: 'invalid', error: 'time.schedule.errors.ambiguous_unit', count: matches.length };
    }

    return { ...parsed, unit: code };
}

function cellErrorTitle(parsed, payload) {
    const template = payload.error_messages?.[parsed.error] || payload.invalid_message || '';

    return template.replaceAll(':count', String(parsed.count ?? ''));
}

function isWeekendIso(isoDate) {
    const match = String(isoDate).match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) {
        return false;
    }
    const weekday = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])).getDay();

    return weekday === 0 || weekday === 6;
}

function weekendColumnIndexes(payload) {
    return new Set(
        (payload.dates ?? []).flatMap((date, index) => (isWeekendIso(date) ? [index + 1] : [])),
    );
}

function applyWeekendColumns(worksheet, weekendCols) {
    if (Array.isArray(worksheet.headers)) {
        worksheet.headers.forEach((header, colIndex) => {
            header?.classList.toggle('wp-roster-col--weekend', weekendCols.has(colIndex));
        });
    }
    if (Array.isArray(worksheet.cols)) {
        worksheet.cols.forEach((col, colIndex) => {
            col?.colElement?.classList.toggle('wp-roster-col--weekend', weekendCols.has(colIndex));
        });
    }
}

/**
 * Toon groepscode onder de dienst (smaller), cell-value blijft D1/G1 voor edit/save.
 */
function paintCellDisplay(cell, value) {
    const text = cellText(value).trim();
    const split = text === '' ? null : splitDutyAndUnit(text);
    const hadStack = cell.classList.contains('wp-roster-cell--stacked');

    if (split && split[1] && !text.includes('\n')) {
        const duty = split[0];
        const unit = split[1].toUpperCase();
        cell.classList.add('wp-roster-cell--stacked');
        cell.replaceChildren();
        const dutyEl = document.createElement('span');
        dutyEl.className = 'wp-roster-cell__duty';
        dutyEl.textContent = duty;
        const unitEl = document.createElement('span');
        unitEl.className = 'wp-roster-cell__unit';
        unitEl.textContent = unit;
        cell.append(dutyEl, unitEl);

        return;
    }

    cell.classList.remove('wp-roster-cell--stacked');
    if (hadStack || cell.querySelector('.wp-roster-cell__duty')) {
        cell.textContent = cellText(value);
    }
}

function applyCellClasses(worksheet, payload) {
    if (!worksheet || typeof worksheet.getData !== 'function') {
        return;
    }
    const types = payload.types ?? [];
    const weekendCols = weekendColumnIndexes(payload);
    applyWeekendColumns(worksheet, weekendCols);
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
            cell.classList.toggle('wp-roster-col--weekend', weekendCols.has(colIndex));
            paintCellDisplay(cell, value);
            const worker = payload.workers?.[rowIndex];
            const parsed = parseRosterCell(value, types, catalogForWorker(worker, payload.units));
            const date = payload.dates?.[colIndex - 1];
            const attendanceKey = worker && date ? `${worker.id}:${date}` : '';
            const attendance = attendanceKey ? payload.attendance?.[attendanceKey] : null;
            if (parsed.kind === 'invalid') {
                cell.classList.add('wp-roster-cell--invalid');
                cell.title = cellErrorTitle(parsed, payload);
                cell.setAttribute('aria-invalid', 'true');
            } else if (parsed.color && parsed.color !== 'none') {
                cell.classList.add(`wp-roster-cell--${parsed.color}`);
                cell.removeAttribute('aria-invalid');
                if (attendance && attendance !== 'none' && attendance !== 'ok') {
                    cell.classList.add(`wp-roster-cell--${attendance}`);
                    cell.title = payload.attendance_messages?.[attendance] || '';
                } else {
                    cell.removeAttribute('title');
                }
            } else {
                cell.removeAttribute('aria-invalid');
                if (attendance && attendance !== 'none' && attendance !== 'ok') {
                    cell.classList.add(`wp-roster-cell--${attendance}`);
                    cell.title = payload.attendance_messages?.[attendance] || '';
                } else {
                    cell.removeAttribute('title');
                }
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
                raw: cellText(raw),
            });
        });
    });

    return cells;
}

function hasInvalidCells(worksheet, payload) {
    return collectCells(worksheet, payload).some((cell) => {
        const worker = payload.workers.find((item) => item.id === cell.worker_id);

        return parseRosterCell(cell.raw, payload.types ?? [], catalogForWorker(worker, payload.units)).kind === 'invalid';
    });
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
                value = cellText(value);
                if (value.trim().startsWith('=')) {
                    return false;
                }
                if (!value.includes('-')) {
                    return value.trim().toUpperCase();
                }

                return value;
            },
            onbeforepaste: (_instance, data) => pasteGrid(data).map((line) => line.slice(0, dayCount)),
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
