# Manually Approving New Users

Mbin allows you to manually approve new users before they can log into your server.

If you want to manually approve users before they can log into your server, 
you can either tick the 'New users have to be approved by an admin before they can log in' checkbox in the admin settings.   
Or put this in the `.env` file:

```ini
MBIN_NEW_USERS_NEED_APPROVAL=true
```

The admin will then see a new 'Signup request' panel in the admin interface where new user registrations will appear pending your approval or denial.

When an administrator approves or denies an user application, the user will receive an email notification about the decision.
