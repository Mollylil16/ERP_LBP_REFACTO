import { NestFactory } from '@nestjs/core';
import { AppModule } from './src/app.module';
import { FacturesService } from './src/factures/factures.service';
import * as fs from 'fs';

async function bootstrap() {
    const app = await NestFactory.createApplicationContext(AppModule);
    const service = app.get(FacturesService);

    try {
        console.log('Testing PDF generation for Invoice #4...');
        const buffer = await service.generatePDF(4);
        fs.writeFileSync('test-invoice-4.pdf', buffer);
        console.log('✅ PDF generated successfully: test-invoice-4.pdf (' + buffer.length + ' bytes)');
    } catch (error) {
        console.error('❌ PDF generation failed:', error);
    } finally {
        await app.close();
    }
}

bootstrap();
