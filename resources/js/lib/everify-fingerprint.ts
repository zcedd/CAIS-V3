export type EverifyFingerprintConfig = {
    ports: number[];
    env: string;
    domain_uri: string;
    device_id: string | null;
};

export type EverifyFingerprintCapture = {
    biometrics: Array<Record<string, unknown>>;
};

const EMPTY_HASH =
    'E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855';

export async function captureEverifyFingerprint(
    config: EverifyFingerprintConfig,
    deviceId: string,
): Promise<EverifyFingerprintCapture> {
    const serial = deviceId.trim();

    if (serial === '') {
        throw new Error('Enter the fingerprint scanner serial number.');
    }

    const ports = config.ports.length > 0 ? config.ports : [4301, 4302];
    const body = JSON.stringify({
        env: config.env,
        purpose: 'Auth',
        specVersion: '0.9.5',
        timeout: 10000,
        captureTime: new Date().toUTCString(),
        domainUri: config.domain_uri,
        transactionId: Date.now(),
        bio: [
            {
                type: 'Finger',
                count: 1,
                bioSubType: ['UNKNOWN'],
                requestedScore: 40,
                deviceId: serial,
                deviceSubId: 0,
                previousHash: EMPTY_HASH,
            },
        ],
        customOpts: null,
    });

    let lastError = 'No fingerprint scanner responded.';

    for (const port of ports) {
        try {
            const response = await fetch(`http://127.0.0.1:${port}/capture`, {
                method: 'CAPTURE',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body,
            } as RequestInit);

            if (!response.ok) {
                lastError = `The scanner on port ${port} returned ${response.status}.`;
                continue;
            }

            const payload = (await response.json()) as EverifyFingerprintCapture;

            if (!Array.isArray(payload.biometrics) || payload.biometrics.length === 0) {
                lastError = 'The scanner did not return fingerprint data.';
                continue;
            }

            return payload;
        } catch {
            lastError = `Could not reach the scanner on port ${port}.`;
        }
    }

    throw new Error(lastError);
}
