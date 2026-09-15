let loading: Promise<void> | null = null;

export async function loadEverifyLivenessSdk(src: string): Promise<void> {
    if (typeof window.eKYC === 'function') {
        return;
    }

    if (loading) {
        await loading;

        return;
    }

    loading = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () =>
            reject(new Error('Could not load the eVerify face check.'));
        document.head.appendChild(script);
    });

    await loading;
}

export async function startEverifyLiveness(
    publicKey: string,
    sdkUrl: string,
): Promise<string> {
    await loadEverifyLivenessSdk(sdkUrl);

    if (typeof window.eKYC !== 'function') {
        throw new Error('The eVerify face check is not available.');
    }

    const response = await window.eKYC().start({ pubKey: publicKey });

    if (response.status !== 'COMPLETED' || !response.result?.session_id) {
        throw new Error('The face check did not finish. Try again.');
    }

    return response.result.session_id;
}
