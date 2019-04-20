# Videoportal

This is a simple video portal for videos in a simple database. I've only tested this with PHP 7 and a sqlite3 database, but it may work with other dbs and php versions too.


## Configuration

Just set the correct DB in db and create the db.


## Database

The database can be created using the script in the "videodb" branch. You can also create it manually, using the following SQL statements:

```
CREATE TABLE video (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    name VARCHAR(32) NOT NULL,
    date DATETIME
  );

CREATE TABLE source (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    video INTEGER NOT NULL,
    location VARCHAR(32) NOT NULL UNIQUE,
    type VARCHAR(1) NOT NULL,
    mime VARCHAR(16) NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    duration INTEGER NOT NULL,
    generated INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (video) REFERENCES video(id) ON DELETE CASCADE ON UPDATE CASCADE
  );

CREATE TABLE property (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    name VARCHAR(256) UNIQUE NOT NULL
  );

CREATE TABLE video_property (
    property INTEGER NOT NULL,
    video INTEGER NOT NULL,
    is_category INTEGER NOT NULL DEFAULT 1,
    identifying INTEGER NOT NULL DEFAULT 0,
    value VARCHAR(256) NOT NULL DEFAULT '',
    FOREIGN KEY (video) REFERENCES video(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (property) REFERENCES property(id) ON DELETE CASCADE ON UPDATE CASCADE,
    PRIMARY KEY (property, video, value)
  );

INSERT INTO property (name) VALUES ('collector');
```

There are still a lot of things I want to improve, and some of them may require me to change the DB schema slightly.
I recommend making sure the dataset can be regenerated at any time to make upgrades simpler.

The name of a video may not be unique, but the set of name and video_property entries with the identifying property set should be.

The video properties are used to categorize, group and add other information about videos. The video property value is optional,
if the name is used for the category type (episode,channel,etc.), the value can be used as category name (episod1, channel abc, ...).
Only properties with is_category are displayed in the menu on the side of the video portal, but videos can still be grouped/filtered
by any property.

Every video must have the special property 'collector', which should be an identifying property.
The collector is intended as the overall category or source of media. For example, you could have one
for recorded TV movies, one for copied DVDs, one for mirrored YouTube videos, etc. It's for different
kinds of media that you may not want to mix.

There is also the special property 'series'. Only use it on all or none of the videos of a collector.
If this property exists for the videos of the selected collector, the default view changes from all
videos to the series category view. This is useful for automatically grouping stuff like movies and their sequals.

If there is only one video in a category/with a certain property, the video portal won't display that property/video group,
but directly the only video contained in it.

## Other things

It can also be added to the desktop of android devices, in which cases it will appear almost like a real app.
