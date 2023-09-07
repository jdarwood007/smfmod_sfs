# Stop Forum Spam
### Description
This customization adds support for detecting spam by using the [Stop Forum Spam](https://www.stopforumspam.com/) API.

### SMF Version support
This supports SMF 2.0.x and 2.1.x

### Sections checked
On registration this can check the following fields:
- Username
- Email Address
- IP Address

On Posts this can check the following fields
- Username
- Email Address
- IP Address

On Search this can check:
- IP Address

On Report(ing) posts this can check:
- Email
- IP Address

This can also check custom forms by specifying the id of the field in a comma separated list into the extra fields options.

### Setup
Out of the box this has a default configuration of checking only usernames.  Additionally the confidence level for username can be adjusted.

This can also block TOR as reported to the Stop Forum Spam database.

The Verification Options section controls on which controls we are enforcing these checks again.

### Compatibility
This has some setting compatibility with the original Stop Forum Spam as I was using it when I developed this.  The biggest difference is this supports SMF 2.1 and does not use any edits.  This version includes logging, bulk checks and the ability to check non standard SMF verification fields.
