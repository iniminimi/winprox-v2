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
    const normalized = raw.replace(/\r\n|\r|\n/g, '/');
    if (normalized.includes('/')) {
        const parts = normalized.split('/').map((part) => part.trim());
        if (parts.length !== 2 || parts[0] === '' || parts[1] === '' || parts[1].includes('/')) {
            return null;
        }

        return [parts[0], parts[1]];
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

/** Celwaarde voor de grid: dienst + newline + groep (smalle kolommen). */
function formatCellDisplay(raw) {
    const text = cellText(raw).trim();
    if (text === '') {
        return '';
    }
    const split = splitDutyAndUnit(text);
    if (!split || !split[1]) {
        return text.replace(/\r\n|\r|\n/g, ' ').trim();
    }

    return `${split[0]}\n${split[1].toUpperCase()}`;
}

/** Terug naar canonical D1/G1 voor opslaan/API. */
function canonicalizeCellValue(raw) {
    const text = cellText(raw).trim();
    if (text === '') {
        return '';
    }
    const split = splitDutyAndUnit(text);
    if (!split || !split[1]) {
        return text.replace(/\r\n|\r|\n/g, ' ').trim();
    }

    return `${split[0]}/${split[1].toUpperCase()}`;
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

function localTodayIso() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

function todayColumnIndexes(payload) {
    const today = localTodayIso();

    return new Set(
        (payload.dates ?? []).flatMap((date, index) => (date === today ? [index + 1] : [])),
    );
}

/** Kolommen die een nieuwe week starten na een gap (vrijdag → maandag zonder weekends). */
function weekStartColumnIndexes(payload) {
    const dates = payload.dates ?? [];
    const cols = new Set();
    for (let i = 1; i < dates.length; i += 1) {
        const prev = Date.parse(`${dates[i - 1]}T12:00:00`);
        const cur = Date.parse(`${dates[i]}T12:00:00`);
        if (!Number.isFinite(prev) || !Number.isFinite(cur)) {
            continue;
        }
        if ((cur - prev) / 86400000 > 1) {
            cols.add(i + 1);
        }
    }

    return cols;
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

function applyTodayColumns(worksheet, todayCols) {
    if (Array.isArray(worksheet.headers)) {
        worksheet.headers.forEach((header, colIndex) => {
            header?.classList.toggle('wp-roster-col--today', todayCols.has(colIndex));
        });
    }
    if (Array.isArray(worksheet.cols)) {
        worksheet.cols.forEach((col, colIndex) => {
            col?.colElement?.classList.toggle('wp-roster-col--today', todayCols.has(colIndex));
        });
    }
}

function applyWeekStartColumns(worksheet, weekStartCols) {
    if (Array.isArray(worksheet.headers)) {
        worksheet.headers.forEach((header, colIndex) => {
            header?.classList.toggle('wp-roster-col--week-start', weekStartCols.has(colIndex));
        });
    }
    if (Array.isArray(worksheet.cols)) {
        worksheet.cols.forEach((col, colIndex) => {
            col?.colElement?.classList.toggle('wp-roster-col--week-start', weekStartCols.has(colIndex));
        });
    }
}

function rosterTd(cell) {
    if (!cell) {
        return null;
    }
    if (cell.tagName === 'TD') {
        return cell;
    }

    return cell.closest?.('td') ?? cell;
}

/**
 * Groep kleiner onder de code. Eén wrapper, idempotent — geen flex op de TD
 * (dat gaf eerder herhaalde stacks in Jspreadsheet).
 */
function paintStackedCell(td, value) {
    const text = cellText(value).trim();
    const split = text === '' ? null : splitDutyAndUnit(text);
    const key = split && split[1] ? `${split[0]}/${split[1].toUpperCase()}` : '';

    if (!key) {
        if (td.dataset.wpStack) {
            delete td.dataset.wpStack;
            td.textContent = text;
        }

        return false;
    }

    if (td.dataset.wpStack === key && td.querySelector(':scope > .wp-roster-cell__stack')) {
        return true;
    }

    const wrap = document.createElement('span');
    wrap.className = 'wp-roster-cell__stack';
    const dutyEl = document.createElement('span');
    dutyEl.className = 'wp-roster-cell__duty';
    dutyEl.textContent = split[0];
    const unitEl = document.createElement('span');
    unitEl.className = 'wp-roster-cell__unit';
    unitEl.textContent = split[1].toUpperCase();
    wrap.append(dutyEl, unitEl);
    td.replaceChildren(wrap);
    td.dataset.wpStack = key;

    return true;
}

function applyCellClasses(worksheet, payload) {
    if (!worksheet || typeof worksheet.getData !== 'function') {
        return;
    }
    const types = payload.types ?? [];
    const weekendCols = weekendColumnIndexes(payload);
    const todayCols = todayColumnIndexes(payload);
    const weekStartCols = weekStartColumnIndexes(payload);
    applyWeekendColumns(worksheet, weekendCols);
    applyTodayColumns(worksheet, todayCols);
    applyWeekStartColumns(worksheet, weekStartCols);
    const rows = worksheet.getData() ?? [];
    rows.forEach((row, rowIndex) => {
        row.forEach((value, colIndex) => {
            const cell = rosterTd(
                typeof worksheet.getCellFromCoords === 'function'
                    ? worksheet.getCellFromCoords(colIndex, rowIndex)
                    : null,
            );
            if (!cell) {
                return;
            }
            if (colIndex === 0) {
                const rowDef = gridRowDefs(payload)[rowIndex];
                cell.classList.toggle('wp-roster-row--section', rowDef?.type === 'section');

                return;
            }
            [...cell.classList].forEach((name) => {
                if (name.startsWith('wp-roster-cell--')) {
                    cell.classList.remove(name);
                }
            });
            cell.classList.toggle('wp-roster-col--weekend', weekendCols.has(colIndex));
            cell.classList.toggle('wp-roster-col--today', todayCols.has(colIndex));
            cell.classList.toggle('wp-roster-col--week-start', weekStartCols.has(colIndex));
            if (paintStackedCell(cell, value)) {
                cell.classList.add('wp-roster-cell--stacked');
            }
            const rowDef = gridRowDefs(payload)[rowIndex];
            if (rowDef?.type === 'section') {
                cell.classList.add('wp-roster-row--section');
                cell.removeAttribute('aria-invalid');
                cell.removeAttribute('title');

                return;
            }
            const worker = workerById(payload)[rowDef?.worker_id];
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

function workerById(payload) {
    const map = {};
    (payload.workers ?? []).forEach((worker) => {
        map[worker.id] = worker;
    });

    return map;
}

function gridRowDefs(payload) {
    if (Array.isArray(payload.rows) && payload.rows.length > 0) {
        return payload.rows;
    }

    return (payload.workers ?? []).map((worker) => ({
        type: 'worker',
        worker_id: worker.id,
    }));
}

function collectCells(worksheet, payload) {
    const cells = [];
    const byId = workerById(payload);
    gridRowDefs(payload).forEach((row, rowIndex) => {
        if (row.type !== 'worker') {
            return;
        }
        const worker = byId[row.worker_id];
        if (!worker) {
            return;
        }
        payload.dates.forEach((date, dayIndex) => {
            const raw = worksheet.getValueFromCoords(dayIndex + 1, rowIndex);
            cells.push({
                worker_id: worker.id,
                date,
                raw: canonicalizeCellValue(raw),
            });
        });
    });

    return cells;
}

function hasInvalidCells(worksheet, payload) {
    const byId = workerById(payload);

    return collectCells(worksheet, payload).some((cell) => {
        const worker = byId[cell.worker_id];

        return parseRosterCell(cell.raw, payload.types ?? [], catalogForWorker(worker, payload.units)).kind === 'invalid';
    });
}

function buildData(payload) {
    const byId = workerById(payload);

    return gridRowDefs(payload).map((row) => {
        if (row.type === 'section') {
            return [row.label || '', ...payload.dates.map(() => '')];
        }
        const worker = byId[row.worker_id];
        const line = [worker?.name ?? ''];
        payload.dates.forEach((date) => {
            const key = `${row.worker_id}:${date}`;
            line.push(formatCellDisplay(payload.cells[key]?.display ?? ''));
        });

        return line;
    });
}

function rowTitleOptions(payload) {
    const rows = {};
    let personNo = 0;
    gridRowDefs(payload).forEach((row, rowIndex) => {
        if (row.type === 'section') {
            rows[rowIndex] = { title: '\u00a0' };
        } else {
            personNo += 1;
            rows[rowIndex] = { title: String(personNo) };
        }
    });

    return rows;
}

function applySectionRowStyles(worksheet, payload) {
    const dayCount = payload.dates?.length ?? 0;
    let personNo = 0;
    gridRowDefs(payload).forEach((row, rowIndex) => {
        const isSection = row.type === 'section';
        const tr = worksheet.rows?.[rowIndex]?.element
            ?? rosterTd(typeof worksheet.getCellFromCoords === 'function'
                ? worksheet.getCellFromCoords(0, rowIndex)
                : null)?.closest('tr')
            ?? null;
        if (tr) {
            tr.classList.toggle('wp-roster-row--section', isSection);
            const indexTd = tr.children[0];
            if (indexTd) {
                indexTd.classList.toggle('wp-roster-row--section', isSection);
                if (isSection) {
                    indexTd.textContent = '\u00a0';
                } else {
                    personNo += 1;
                    indexTd.textContent = String(personNo);
                    indexTd.classList.remove('wp-roster-row--section');
                }
            }
        }
        for (let colIndex = 0; colIndex <= dayCount; colIndex += 1) {
            const cell = typeof worksheet.getCellFromCoords === 'function'
                ? rosterTd(worksheet.getCellFromCoords(colIndex, rowIndex))
                : null;
            if (!cell) {
                continue;
            }
            cell.classList.toggle('wp-roster-row--section', isSection);
            if (isSection && typeof worksheet.setReadOnly === 'function') {
                worksheet.setReadOnly(colIndex, rowIndex, true);
            }
        }
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
                title: payload.name_column || 'Name',
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

        const rowDefs = gridRowDefs(payload);
        const worksheetConfig = {
            data: buildData(payload),
            columns,
            rows: rowTitleOptions(payload),
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
            minDimensions: [dayCount + 1, Math.max(rowDefs.length, 1)],
        };
        if (isMonth && payload.month_label) {
            worksheetConfig.nestedHeaders = [[
                { title: '', colspan: 1 },
                { title: payload.month_label, colspan: dayCount },
            ]];
        }

        const paint = (instance) => {
            applyCellClasses(instance, payload);
            applySectionRowStyles(instance, payload);
        };

        const instances = jspreadsheet(grid, {
            worksheets: [worksheetConfig],
            contextMenu: () => false,
            onbeforechange: (_instance, _cell, x, y, value) => {
                const rowDef = gridRowDefs(payload)[Number(y)];
                if (rowDef?.type === 'section' || Number(x) === 0) {
                    return false;
                }
                value = cellText(value);
                if (value.trim().startsWith('=')) {
                    return false;
                }
                if (!value.includes('-')) {
                    value = value.trim().toUpperCase();
                }

                return formatCellDisplay(value);
            },
            onbeforepaste: (_instance, data) => pasteGrid(data)
                .map((line) => line.slice(0, dayCount).map((value) => formatCellDisplay(value))),
            onafterchanges: (instance) => {
                paint(instance);
            },
            onload: (instance) => {
                paint(instance);
            },
        });

        worksheet = Array.isArray(instances) ? instances[0] : instances;
        paint(worksheet);
        requestAnimationFrame(() => {
            if (worksheet) {
                paint(worksheet);
            }
            if (isMonth) {
                fitMonthColumns(worksheet, grid, dayCount);
                requestAnimationFrame(() => worksheet && paint(worksheet));
            }
        });
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
