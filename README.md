Qc References
==============================================================
*La [version française](#documentation-qc-references) de la documentation suit le texte anglais*

## About
This extension extend the Info Module and is used to display the references of a selected page in the Pagetree, even if you don't have access to the content. It shows the following information:

- BE group : Displays the group name the pointing page responsible for the reference.

- Table : The table name having the link, for example tt_content or pages.

- Page UID : Displays the UID of the reference page or the tt_content PID.

- Slug : Clickable slug so you can check in FE the context in which the reference came from.


Note : The reference source state will be showned: disabled or expired if it's the case.

After installing this extension, references for a selected page can be found in the Info module.


## Page TSconfig
The extension offers two parameters:

### Select the tables to check for references

```php
# Tables used to check. Default pages and tt_content
mod.qcReferences.allowedTables = pages, tt_content
```

### Control how many items to display
```php
# How many items per pages to display. Default is 100.
mod.qcReferences.itemsPerPage = 100
```


### Screenshot of the references

![List of references](Documentation/Images/qc-references.png)


-----------
[Version française]
# Documentation Qc References

## À propos
Cette extension ajoute une option au module Info et sert à afficher les références d’une page sélectionnée dans l'Arborescence des pages même si vous n'avez pas accès au contenu y pointant. L'information suivante est affichée dans le module:  

- Le titre : Titre de la page ou le contenu(tt_content) qui fait référence à la page sélectionnée.

- Groupe BE : Le nom du groupe responsable de la référence.

- Table : La table contenant la référence, par exemple tt_content ou pages.

- UID de page : L'identifiant de la page de référence ou du contenu de la page contenant la référence.

- Slug : Le chemin(slug) cliquable pour permettre de visualiser la page du site dans son contexte "frontend".


NB : L'état désactivé ou masqué est aussi visible pour la référence.

Après l’installation de cette extension, les références à la page sélectionnée sont accessibles par le sous-menu "Références à la page" du Module Info.

### Configuration TS 
L’extension offre deux options de configuration: 

#### Sélectionner les types des références 

```php
# Choix des tables à vérifier pour les références
mod.qcReferences.allowedTables = pages, tt_content
```
#### Contrôler l'affichage de résultat : 
```php
# Quantité de lignes à afficher
mod.qcReferences.itemsPerPage = 100
```
