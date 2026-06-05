/**
 * Input Validation and Sanitization Utilities
 * Comprehensive client-side validation to prevent XSS, invalid data, and improve UX
 */

/**
 * Sanitize text input to prevent XSS attacks
 */
export const sanitizeText = (input, maxLength = null) => {
    if (!input) return '';

    let sanitized = String(input);

    // Remove script tags and content
    sanitized = sanitized.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');

    // Remove HTML tags
    sanitized = sanitized.replace(/<[^>]+>/g, '');

    // Decode HTML entities
    const textarea = document.createElement('textarea');
    textarea.innerHTML = sanitized;
    sanitized = textarea.value;

    // Remove control characters except newlines and tabs
    sanitized = sanitized.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');

    sanitized = sanitized.trim();

    if (maxLength && sanitized.length > maxLength) {
        sanitized = sanitized.substring(0, maxLength);
    }

    return sanitized;
};

/**
 * Validate and sanitize amount input
 */
export const validateAmount = (amount, allowNegative = false, maxValue = 999999999.99) => {
    if (amount === '' || amount === null || amount === undefined) {
        return { isValid: false, value: null, error: 'Amount is required' };
    }

    const numAmount = parseFloat(amount);

    if (isNaN(numAmount)) {
        return { isValid: false, value: null, error: 'Amount must be a valid number' };
    }

    if (!allowNegative && numAmount < 0) {
        return { isValid: false, value: null, error: 'Amount cannot be negative' };
    }

    if (numAmount === 0) {
        return { isValid: false, value: null, error: 'Amount must be greater than zero' };
    }

    if (Math.abs(numAmount) > maxValue) {
        return { isValid: false, value: null, error: `Amount cannot exceed ${maxValue.toLocaleString()}` };
    }

    const roundedAmount = Math.round(numAmount * 100) / 100;

    return { isValid: true, value: roundedAmount, error: null };
};

/**
 * Validate date input
 */
export const validateDate = (dateString, allowFuture = false, maxPastYears = 10,time='') => {
    if (!dateString) {
        return { isValid: false, value: null, error: 'Date is required' };
    }

    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(dateString)) {
        return { isValid: false, value: null, error: 'Invalid date format' };
    }

    const inputDate = new Date(dateString);

    if (isNaN(inputDate.getTime())) {
        return { isValid: false, value: null, error: 'Invalid date' };
    }

    // Check for impossible dates
    const [year, month, day] = dateString.split('-').map(Number);
    if (inputDate.getFullYear() !== year ||
        inputDate.getMonth() !== month - 1 ||
        inputDate.getDate() !== day) {
        return { isValid: false, value: null, error: 'Invalid date (impossible date)' };
    }

    const today = new Date();
    const inputDateTime = time ? new Date(`${dateString}T${time}`) : inputDate;

    if (!allowFuture && inputDateTime > today) {
        return { isValid: false, value: null, error: 'Future dates are not allowed' };
    }

    const minDate = new Date();
    minDate.setFullYear(minDate.getFullYear() - maxPastYears);
    if (inputDate < minDate) {
        return { isValid: false, value: null, error: `Date cannot be more than ${maxPastYears} years in the past` };
    }

    return { isValid: true, value: dateString, error: null };
};

/**
 * Validate time input
 */
export const validateTime = (timeString) => {
    if (!timeString) {
        return { isValid: true, value: null, error: null };
    }

    const timeRegex = /^([01]\d|2[0-3]):([0-5]\d)$/;
    if (!timeRegex.test(timeString)) {
        return { isValid: false, value: null, error: 'Invalid time format' };
    }

    return { isValid: true, value: timeString, error: null };
};

/**
 * Validate description/notes fields
 */
export const validateDescription = (text, maxLength = 1000, required = false) => {
    const sanitized = sanitizeText(text, maxLength);

    if (required && !sanitized) {
        return { isValid: false, value: sanitized, error: 'This field is required' };
    }

    if (sanitized.length > maxLength) {
        return { isValid: false, value: sanitized, error: `Maximum length is ${maxLength} characters` };
    }

    return { isValid: true, value: sanitized, error: null };
};

/**
 * Validate name fields
 */
export const validateName = (name, minLength = 2, maxLength = 100) => {
    const sanitized = sanitizeText(name, maxLength);

    if (!sanitized) {
        return { isValid: false, value: sanitized, error: 'Name is required' };
    }

    if (sanitized.length < minLength) {
        return { isValid: false, value: sanitized, error: `Name must be at least ${minLength} characters` };
    }

    if (sanitized.length > maxLength) {
        return { isValid: false, value: sanitized, error: `Name cannot exceed ${maxLength} characters` };
    }

    return { isValid: true, value: sanitized, error: null };
};

/**
 * Validate code fields
 */
export const validateCode = (code, maxLength = 20) => {
    if (!code) {
        return { isValid: false, value: '', error: 'Code is required' };
    }

    let sanitized = String(code).trim().toUpperCase();
    sanitized = sanitized.replace(/[^A-Z0-9_-]/g, '');

    if (!sanitized) {
        return { isValid: false, value: sanitized, error: 'Code must contain alphanumeric characters' };
    }

    if (sanitized.length > maxLength) {
        return { isValid: false, value: sanitized, error: `Code cannot exceed ${maxLength} characters` };
    }

    return { isValid: true, value: sanitized, error: null };
};

/**
 * Validate IFSC code
 */
export const validateIFSC = (ifsc, required = false) => {
    if (!ifsc) {
        if (required) {
            return { isValid: false, value: '', error: 'IFSC code is required' };
        }
        return { isValid: true, value: '', error: null };
    }

    const sanitized = String(ifsc).trim().toUpperCase();
    const ifscRegex = /^[A-Z]{4}0[A-Z0-9]{6}$/;

    if (!ifscRegex.test(sanitized)) {
        return { isValid: false, value: sanitized, error: 'Invalid IFSC code format (e.g., SBIN0001234)' };
    }

    return { isValid: true, value: sanitized, error: null };
};

/**
 * Validate tags
 */
export const validateTags = (tags, maxTags = 10, maxTagLength = 30) => {
    if (!tags) {
        return { isValid: true, value: '', error: null };
    }

    const tagArray = String(tags).split(',')
        .map(tag => sanitizeText(tag.trim(), maxTagLength))
        .filter(tag => tag.length > 0);

    if (tagArray.length > maxTags) {
        return { isValid: false, value: tags, error: `Maximum ${maxTags} tags allowed` };
    }

    const sanitizedTags = tagArray.join(', ');
    return { isValid: true, value: sanitizedTags, error: null };
};

/**
 * Real-time amount input handler.
 * Either: handleAmountInput(e, (value) => { ... })  OR  handleAmountInput(e, setData, 'fieldKey') for useForm.setData.
 */
export const handleAmountInput = (event, setter, fieldKey = undefined) => {
    let value = event.target.value;

    value = value.replace(/[^\d.-]/g, '');

    const parts = value.split('.');
    if (parts.length > 2) {
        value = parts[0] + '.' + parts.slice(1).join('');
    }

    if (parts.length === 2 && parts[1].length > 2) {
        value = parts[0] + '.' + parts[1].substring(0, 2);
    }

    if (value.indexOf('-') > 0) {
        value = value.replace(/-/g, '');
    }

    if (typeof fieldKey === 'string') {
        setter(fieldKey, value);
    } else {
        setter(value);
    }
};

export const TRANSACTION_ERROR_ORDER = [
    'transaction_date',
    'expensed_date',
    'transaction_time',
    'amount',
    'account_id',
    'transfer_to_account_id',
    'payment_method',
    'category',
    'category_id',
    'description',
    'payee_payer',
    'reference_number',
    'tax',
    'status',
    'tags',
    'notes',
    'transfer',
];

export const getTransactionErrorLabels = (transactionType = 'expense') => ({
    transaction_date: 'Date',
    expensed_date: 'Date',
    transaction_time: 'Time',
    amount: 'Amount',
    account_id: transactionType === 'transfer' ? 'From Account' : 'Account',
    transfer_to_account_id: 'To Account',
    payment_method: 'Payment Method',
    category: 'Category',
    category_id: transactionType === 'income' ? 'Income Category' : 'Subcategory',
    description: 'Description',
    payee_payer: 'Payee/Payer',
    reference_number: 'Reference Number',
    tax: 'Tax Amount',
    status: 'Status',
    tags: 'Tags',
    notes: 'Notes',
    transfer: 'Transfer',
});

export const getErrorEntries = (validationErrors = {}, serverErrors = {}, errorOrder = TRANSACTION_ERROR_ORDER) => {
    const combinedErrors = { ...validationErrors, ...serverErrors };

    return [
        ...errorOrder
            .filter((field) => combinedErrors[field])
            .map((field) => [field, combinedErrors[field]]),
        ...Object.entries(combinedErrors)
            .filter(([field, message]) => message && !errorOrder.includes(field)),
    ];
};

export const setValidationFieldError = (setValidationErrors, field, message) => {
    setValidationErrors((current) => ({ ...current, [field]: message || null }));
};

export const clearValidationFieldError = (setValidationErrors, field) => {
    setValidationFieldError(setValidationErrors, field, null);
};

export const clearValidationFieldErrors = (setValidationErrors, fields) => {
    setValidationErrors((current) => ({
        ...current,
        ...Object.fromEntries(fields.map((field) => [field, null])),
    }));
};

export const validateTransactionForm = (data, {
    transactionType = 'expense',
    selectedCategory = '',
    dateField = 'transaction_date',
    dateAllowFuture = false,
    includeTimeInDate = false,
    maxPastYears = 10,
    maxAmount = 999999999.99,
    tagsMax = 10,
    tagMaxLength = 30,
    validateNotes = false,
} = {}) => {
    const nextErrors = {};

    const amountValidation = validateAmount(data.amount, false, maxAmount);
    if (!amountValidation.isValid) {
        nextErrors.amount = amountValidation.error;
    }

    const dateValidation = validateDate(
        data[dateField],
        dateAllowFuture,
        maxPastYears,
        includeTimeInDate ? data.transaction_time : ''
    );
    if (!dateValidation.isValid) {
        nextErrors[dateField] = dateValidation.error;
    }

    const timeValidation = validateTime(data.transaction_time);
    if (!timeValidation.isValid) {
        nextErrors.transaction_time = timeValidation.error;
    }

    if (!data.account_id) {
        nextErrors.account_id = 'Account is required';
    }

    if (transactionType !== 'transfer') {
        if (!data.payment_method) {
            nextErrors.payment_method = 'Payment method is required';
        }
        if (transactionType !== 'income' && !selectedCategory) {
            nextErrors.category = 'Category is required';
        }
        if (!data.category_id) {
            nextErrors.category_id = transactionType === 'income'
                ? 'Income category is required'
                : 'Subcategory is required';
        }
    }

    if (transactionType === 'transfer') {
        if (!data.transfer_to_account_id) {
            nextErrors.transfer_to_account_id = 'Destination account is required';
        }
        if (data.account_id && data.account_id === data.transfer_to_account_id) {
            nextErrors.transfer = 'Source and destination accounts must be different';
        }
    }

    if (data.description) {
        const descValidation = validateDescription(data.description, 1000, false);
        if (!descValidation.isValid) {
            nextErrors.description = descValidation.error;
        }
    }

    if (data.tags) {
        const tagsValidation = validateTags(data.tags, tagsMax, tagMaxLength);
        if (!tagsValidation.isValid) {
            nextErrors.tags = tagsValidation.error;
        }
    }

    if (validateNotes && data.notes) {
        const notesValidation = validateDescription(data.notes, 2000, false);
        if (!notesValidation.isValid) {
            nextErrors.notes = notesValidation.error;
        }
    }

    return nextErrors;
};

/**
 * Get max date (today)
 */
export const getMaxDate = () => {
    const today = new Date();
    return today.toISOString().split('T')[0];
};

/**
 * Get min date (years back)
 */
export const getMinDate = (yearsBack = 10) => {
    const date = new Date();
    date.setFullYear(date.getFullYear() - yearsBack);
    return date.toISOString().split('T')[0];
};

export default {
    sanitizeText,
    validateAmount,
    validateDate,
    validateTime,
    validateDescription,
    validateName,
    validateCode,
    validateIFSC,
    validateTags,
    handleAmountInput,
    TRANSACTION_ERROR_ORDER,
    getTransactionErrorLabels,
    getErrorEntries,
    setValidationFieldError,
    clearValidationFieldError,
    clearValidationFieldErrors,
    validateTransactionForm,
    getMaxDate,
    getMinDate
};
