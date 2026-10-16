/** Normalize common localized decimal keyboards without accepting letters. */
export function decimalText(value) {
    return String(value).replace(/[٠-٩۰-۹０-９]/g, digit => {
        const point = digit.codePointAt(0)
        return String(point - (point >= 0xff10 ? 0xff10 : point >= 0x6f0 ? 0x6f0 : 0x660))
    })
}
