<!DOCTYPE html>
<html lang="fr">
<body>
    <p>Bonjour {{ $etudiant->prenom }} {{ $etudiant->nom }},</p>
    <p>
        Vos notes pour le module <strong>{{ $pv->code_matiere }}</strong>
        ({{ $pv->semestre }}, {{ $pv->annee_academique }}) viennent d'etre publiees.
    </p>
    <p>Connectez-vous a votre espace SIARN pour les consulter.</p>
</body>
</html>
