-- Function to handle new user creation
-- This function is called automatically when a new user is created in auth.users
CREATE OR REPLACE FUNCTION public.handle_new_user()
RETURNS TRIGGER AS $$
BEGIN
  -- Insert into users table
  INSERT INTO public.users (
    id,
    email,
    first_name,
    last_name,
    country,
    is_verified,
    kyc_status,
    kyc_verified,
    created_at
  )
  VALUES (
    NEW.id,
    NEW.email,
    COALESCE(NEW.raw_user_meta_data->>'first_name', split_part(NEW.email, '@', 1)),
    COALESCE(NEW.raw_user_meta_data->>'last_name', 'User'),
    COALESCE(NEW.raw_user_meta_data->>'country', 'US'),
    false,
    'pending',
    false,
    NOW()
  )
  ON CONFLICT (id) DO NOTHING;

  -- Insert into user_security table
  INSERT INTO public.user_security (
    user_id,
    two_fa_enabled,
    failed_login_attempts,
    created_at
  )
  VALUES (
    NEW.id,
    false,
    0,
    NOW()
  )
  ON CONFLICT (user_id) DO NOTHING;

  RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Trigger to call the function when a new user is created
DROP TRIGGER IF EXISTS on_auth_user_created ON auth.users;
CREATE TRIGGER on_auth_user_created
  AFTER INSERT ON auth.users
  FOR EACH ROW
  EXECUTE FUNCTION public.handle_new_user();
