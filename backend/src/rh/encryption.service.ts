import { Injectable, Logger } from '@nestjs/common';
import * as crypto from 'crypto';

const ALGO = 'aes-256-gcm';
const KEY_LEN = 32;
const IV_LEN = 12;
const TAG_LEN = 16;

@Injectable()
export class RhEncryptionService {
  private readonly logger = new Logger(RhEncryptionService.name);
  private readonly key: Buffer;

  constructor() {
    const raw = process.env.RH_ENCRYPTION_KEY ?? '';
    if (raw.length === 64) {
      this.key = Buffer.from(raw, 'hex');
    } else {
      // Dev fallback — warn loudly
      this.logger.warn('RH_ENCRYPTION_KEY not set or invalid (need 64 hex chars). Using insecure dev key.');
      this.key = crypto.scryptSync('dev-key-lbp-rh', 'salt-rh', KEY_LEN);
    }
  }

  encrypt(plaintext: string): string {
    const iv = crypto.randomBytes(IV_LEN);
    const cipher = crypto.createCipheriv(ALGO, this.key, iv);
    const enc = Buffer.concat([cipher.update(plaintext, 'utf8'), cipher.final()]);
    const tag = cipher.getAuthTag();
    return Buffer.concat([iv, tag, enc]).toString('base64');
  }

  decrypt(ciphertext: string): string {
    const buf = Buffer.from(ciphertext, 'base64');
    const iv = buf.subarray(0, IV_LEN);
    const tag = buf.subarray(IV_LEN, IV_LEN + TAG_LEN);
    const enc = buf.subarray(IV_LEN + TAG_LEN);
    const decipher = crypto.createDecipheriv(ALGO, this.key, iv);
    decipher.setAuthTag(tag);
    return decipher.update(enc).toString('utf8') + decipher.final('utf8');
  }

  encryptJson(obj: Record<string, unknown>): string {
    return this.encrypt(JSON.stringify(obj));
  }

  decryptJson(ciphertext: string): Record<string, unknown> {
    return JSON.parse(this.decrypt(ciphertext));
  }
}
