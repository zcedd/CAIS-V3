import * as Sharing from 'expo-sharing';

import { downloadReceipt } from '@/api/mobile';
import { writeReceipt } from '@/evidence/files';

export async function shareOfficialReceipt(url: string, receiptNumber: string | null): Promise<void> {
    const bytes = await downloadReceipt(url);
    const safeName = (receiptNumber ?? `receipt-${Date.now()}`).replace(/[^a-zA-Z0-9-_]/g, '');
    const uri = await writeReceipt(bytes, `${safeName}.pdf`);
    const available = await Sharing.isAvailableAsync();

    if (!available) {
        throw new Error('Sharing is not available on this device.');
    }

    await Sharing.shareAsync(uri, {
        mimeType: 'application/pdf',
        UTI: 'com.adobe.pdf',
        dialogTitle: 'Official receipt',
    });
}
