The WAC website uses the Userpro plugin for:
1. The registration form.
2. Viewing and editing user profiles.
3. Displaying member directories (or directories of class participants, etc.)

The modifications in this folder need to be merged with the Userpro codebase whenever the Userpro plugin is updated.  The modifications I made can generally be found by searching for "daw - wac".  The modifications exist to:
1. Make Userpro member directories aware of wordpress groups, which the WAC website uses to track membership, participation in classes, etc.  
2. Create a more friendly user display-name when a user enrolls.
3. Make password resets easier by putting a link in the reset email.
4. Make directory photos more square instead of round.
