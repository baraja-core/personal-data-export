Personal data export
====================

Idea
----

Tool for easy selection and export of user files in ZIP format.

Within a single selector, you choose all user data (much of the data can also be created dynamically based on direct input), which is automatically converted into the specified directory structure and offers the file for download.

This tool is fully compliant with GDPR requirements.

How to use
----------

Simply select data and run export:

```php
$selection = (new PersonalDataSelection)
    ->addJson('foo.json', ['a' => 1, 'b' => 36])
    ->addJson('dir/file.json', ['message' => 'My content...'])
    ->addText('readme.md', 'Welcome to export!')
    ->addFile(__FILE__);

$selection->export();
```
