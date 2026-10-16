let libPhoneNumberModule = null
let libPhoneNumberPromise = null

export async function loadLibPhoneNumber() {
    if (libPhoneNumberModule) {
        return libPhoneNumberModule
    }

    if (! libPhoneNumberPromise) {
        libPhoneNumberPromise = import('libphonenumber-js/min').then((module) => {
            libPhoneNumberModule = module

            return module
        }).catch(error => {
            libPhoneNumberPromise = null
            throw error
        })
    }

    return libPhoneNumberPromise
}

export async function formatNationalDisplay(national, country) {
    const { parsePhoneNumberFromString, AsYouType } = await loadLibPhoneNumber()
    const digits = String(national ?? '').replace(/\D/g, '')

    if (digits === '') {
        return ''
    }

    const parsed = parsePhoneNumberFromString(digits, country)

    if (parsed) {
        return parsed.formatNational()
    }

    return new AsYouType(country).input(digits)
}

export async function formatPhoneInput(value, country) {
    const { AsYouType } = await loadLibPhoneNumber()
    const formatter = new AsYouType(country)

    return formatter.input(String(value ?? ''))
}

export async function syncPhoneState(rawInput, country) {
    const { AsYouType, parsePhoneNumberFromString } = await loadLibPhoneNumber()
    const digits = String(rawInput ?? '').replace(/\D/g, '')

    if (digits === '') {
        return {
            national: '',
            e164: '',
            formatted: '',
        }
    }

    const formatter = new AsYouType(country)
    const formatted = formatter.input(rawInput)
    const parsed = formatter.getNumber() ?? parsePhoneNumberFromString(rawInput, country)

    return {
        national: parsed?.nationalNumber ?? digits,
        e164: parsed?.number ?? '',
        formatted,
    }
}

