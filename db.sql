-- Drop tables if they exist (order matters for foreign keys)
DROP TABLE IF EXISTS activities CASCADE;
DROP TABLE IF EXISTS files CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- 1. Users table (simplified)
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    country VARCHAR(50),
    bio TEXT,
    avatar_path VARCHAR(10),  -- Stores emoji character (max 10 chars for multi-char emojis)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT check_email_format CHECK (email ~* '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'),
    CONSTRAINT check_phone_format CHECK (phone IS NULL OR phone ~* '^[0-9+\-\s()]{10,20}$')
);

-- 2. Files table (simplified)
CREATE TABLE files (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    file_size BIGINT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    encryption_key VARCHAR(64),  -- hex of 32-byte key
    iv VARCHAR(32),               -- hex of 16-byte IV
    is_favorite BOOLEAN DEFAULT FALSE,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT check_file_size_positive CHECK (file_size >= 0),
    CONSTRAINT check_encryption_fields CHECK (
        (encryption_key IS NULL AND iv IS NULL) OR 
        (encryption_key IS NOT NULL AND iv IS NOT NULL)
    ),
    CONSTRAINT check_encryption_key_length CHECK (encryption_key IS NULL OR LENGTH(encryption_key) = 64),
    CONSTRAINT check_iv_length CHECK (iv IS NULL OR LENGTH(iv) = 32)
);

-- 3. Activities table for logging (simplified)
CREATE TABLE activities (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    activity_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_files_user_id ON files(user_id);
CREATE INDEX idx_files_user_favorite ON files(user_id, is_favorite) WHERE is_favorite = true;
CREATE INDEX idx_files_uploaded_at ON files(uploaded_at DESC);
CREATE INDEX idx_files_user_uploaded ON files(user_id, uploaded_at DESC);
CREATE INDEX idx_files_is_favorite ON files(is_favorite);
CREATE INDEX idx_activities_user_id ON activities(user_id);
CREATE INDEX idx_activities_created_at ON activities(created_at DESC);
CREATE INDEX idx_activities_type ON activities(activity_type);

-- Comments for documentation
COMMENT ON TABLE users IS 'Stores user account information with avatar support';
COMMENT ON COLUMN users.avatar_path IS 'Stores emoji avatar character (e.g., 👤, 👨‍💼, 🐼)';
COMMENT ON TABLE files IS 'Stores file metadata and encryption keys';
COMMENT ON TABLE activities IS 'User activity log';
COMMENT ON COLUMN files.encryption_key IS 'Hex-encoded 32-byte AES-256 key';
COMMENT ON COLUMN files.iv IS 'Hex-encoded 16-byte initialization vector';

-- View sample data
SELECT * FROM users;
SELECT * FROM activities;
SELECT * FROM files;