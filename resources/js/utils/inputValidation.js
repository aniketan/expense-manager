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
 * Real-time amount input handler
 */
export const handleAmountInput = (event, setValue) => {
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

    setValue(value);
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
    getMaxDate,
    getMinDate
};
