/**
 * Pure row update for AI categorization results (extracted from fillWithAI in Review.jsx).
 *
 * When the backend flags the suggestion as a default fallback (`data.fallback === true`),
 * the user's current category selection is left untouched and the row is marked low
 * confidence so the existing confidence badge shows it needs review.
 */
export function applyAiCategorizationToRow(row, data) {
    const base = {
        ...row,
        aiLoading: false,
        aiDone: true,
        aiConfidence: data.fallback ? 'low' : data.confidence,
        aiReason: data.reason,
    };

    if (data.fallback) {
        return base;
    }

    if (row.type === 'income') {
        return {
            ...base,
            category_id: '',
            subcategory_id: data.subcategory_id != null ? String(data.subcategory_id) : '',
        };
    }

    return {
        ...base,
        category_id: data.category_id != null ? String(data.category_id) : '',
        subcategory_id: data.subcategory_id != null ? String(data.subcategory_id) : '',
    };
}
