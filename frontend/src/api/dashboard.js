import { client } from './client';

function requeteString(params) {
  return new URLSearchParams(Object.fromEntries(Object.entries(params).filter(([, v]) => v))).toString();
}

export const obtenirDashboardPv = (params = {}) => {
  const q = requeteString(params);
  return client.get(`/dashboard/pv${q ? `?${q}` : ''}`);
};

export const obtenirDashboardOcr = () => client.get('/dashboard/ocr');

export const exporterPvCsv = (params = {}) => {
  const q = requeteString(params);
  return client.getBlob(`/dashboard/pv/export${q ? `?${q}` : ''}`);
};
