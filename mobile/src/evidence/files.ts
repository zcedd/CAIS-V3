import * as Crypto from 'expo-crypto';
import { Directory, File, Paths } from 'expo-file-system';

function directory(name: string): Directory {
    const folder = new Directory(Paths.document, name);

    if (!folder.exists) {
        folder.create({ intermediates: true, idempotent: true });
    }

    return folder;
}

export async function persistPhoto(sourceUri: string, operationId: string): Promise<string> {
    const destination = new File(directory('evidence'), `${operationId}-photo.jpg`);
    const source = new File(sourceUri);

    if (destination.exists) {
        destination.delete();
    }

    await source.copy(destination);

    return destination.uri;
}

export async function persistSignature(dataUrl: string, operationId: string): Promise<string> {
    const encoded = dataUrl.replace(/^data:image\/[a-zA-Z0-9.+-]+;base64,/, '');
    const destination = new File(directory('evidence'), `${operationId}-signature.png`);

    if (destination.exists) {
        destination.delete();
    }

    destination.create();
    await destination.write(encoded, { encoding: 'base64' });

    return destination.uri;
}

export async function sha256OfFile(uri: string): Promise<string> {
    const file = new File(uri);
    const bytes = await file.bytes();
    const digest = await Crypto.digest(Crypto.CryptoDigestAlgorithm.SHA256, bytes);

    return [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
}

export function deleteEvidence(uri: string | null): void {
    if (!uri) {
        return;
    }

    try {
        const file = new File(uri);

        if (file.exists) {
            file.delete();
        }
    } catch {
        // A missing evidence file should not block removing the local record.
    }
}

export async function writeReceipt(bytes: Uint8Array, name: string): Promise<string> {
    const file = new File(directory('receipts'), name);

    if (file.exists) {
        file.delete();
    }

    file.create();
    await file.write(bytes);

    return file.uri;
}
