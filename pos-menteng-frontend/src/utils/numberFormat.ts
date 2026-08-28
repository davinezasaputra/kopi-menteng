export const formatNumberInput = (value: string, allowDecimal = false) => {
  if (value === '') return '';

  if (!allowDecimal) {
    const digits = value.replace(/\D/g, '');
    return digits ? Number(digits).toLocaleString('id-ID') : '';
  }

  const normalized = value.replace(/[^\d.,]/g, '').replace(',', '.');
  const [integerPart, decimalPart] = normalized.split('.');
  const formattedInteger = integerPart ? Number(integerPart).toLocaleString('id-ID') : '';
  return decimalPart === undefined ? formattedInteger : `${formattedInteger},${decimalPart}`;
};

export const parseNumberInput = (value: string) => Number(value.replace(/\./g, '').replace(',', '.')) || 0;
