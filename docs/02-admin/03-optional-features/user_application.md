# Manually Approving New Users

If you want to manually approve users before they can log into your server, 
you can either tick the checkbox in the admin settings put this in the `.env` file:
```ini
MBIN_NEW_USERS_NEED_APPROVAL=true
```

You will then see a new admin panel called `Applications` where new users will appear until you approve or deny them.
When you have decided on one or the other, the user will get an email notification about it.
