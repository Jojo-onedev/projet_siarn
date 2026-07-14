import { client } from './client';

export const listerModelesOcr = () => client.get('/modeles-ocr');
