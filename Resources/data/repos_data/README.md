##How to add a new JSON file with your repository data##
* Create a json file with the name of your repository. (E.g. CAMPUSDOMAR.json)
* Add the ***title***, ***description*** and ***thumbnail_url*** tags to your json file.

Example:
```
{
    "title": "UVigo-TV",
    "description": "Uvigo-TV is an Internet television service provided by the ATIC of the New Technologies and Quality Vice Rectorate of the University of Vigo",
    "thumbnail_url": "UVIGO.jpg"
}
```

* Create an image file to be used as the thumbnail of your repository, and add the complete file name to the ***thumbnail_url*** field of your JSON file (see example above).

* Make a [pull request](https://help.github.com/articles/creating-a-pull-request/) to this folder with both files: the thumbnail image and the JSON file.