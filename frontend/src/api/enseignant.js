import { client } from './client';

export const listerMesModules = () => client.get('/mes-modules');
export const listerNotesDuModule = (moduleId) => client.get(`/mes-modules/${moduleId}/notes`);
export const signalerFraude = (noteId, motif) => client.post(`/notes/${noteId}/signaler-fraude`, { motif });
