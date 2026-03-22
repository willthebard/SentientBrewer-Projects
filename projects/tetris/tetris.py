import pygame
import random
import sys

# Initialize Pygame
pygame.init()

# Constants
WINDOW_WIDTH = 800
WINDOW_HEIGHT = 600
BOARD_WIDTH = 10
BOARD_HEIGHT = 20
CELL_SIZE = 25
BOARD_X = 300
BOARD_Y = 50

# Colors
BLACK = (0, 0, 0)
WHITE = (255, 255, 255)
GRAY = (128, 128, 128)
DARK_GRAY = (64, 64, 64)
LIGHT_GRAY = (192, 192, 192)

# Tetromino colors
COLORS = {
    'I': (0, 255, 255),    # Cyan
    'O': (255, 255, 0),    # Yellow
    'T': (128, 0, 128),    # Purple
    'S': (0, 255, 0),      # Green
    'Z': (255, 0, 0),      # Red
    'J': (0, 0, 255),      # Blue
    'L': (255, 165, 0),    # Orange
    'EMPTY': BLACK,
    'BORDER': WHITE
}

# Tetromino shapes - each shape has multiple rotation states
TETROMINOES = {
    'I': [
        [[0, 0, 0, 0],
         [1, 1, 1, 1],
         [0, 0, 0, 0],
         [0, 0, 0, 0]],
        [[0, 0, 1, 0],
         [0, 0, 1, 0],
         [0, 0, 1, 0],
         [0, 0, 1, 0]]
    ],
    'O': [
        [[1, 1],
         [1, 1]]
    ],
    'T': [
        [[0, 1, 0],
         [1, 1, 1],
         [0, 0, 0]],
        [[0, 1, 0],
         [0, 1, 1],
         [0, 1, 0]],
        [[0, 0, 0],
         [1, 1, 1],
         [0, 1, 0]],
        [[0, 1, 0],
         [1, 1, 0],
         [0, 1, 0]]
    ],
    'S': [
        [[0, 1, 1],
         [1, 1, 0],
         [0, 0, 0]],
        [[0, 1, 0],
         [0, 1, 1],
         [0, 0, 1]]
    ],
    'Z': [
        [[1, 1, 0],
         [0, 1, 1],
         [0, 0, 0]],
        [[0, 0, 1],
         [0, 1, 1],
         [0, 1, 0]]
    ],
    'J': [
        [[1, 0, 0],
         [1, 1, 1],
         [0, 0, 0]],
        [[0, 1, 1],
         [0, 1, 0],
         [0, 1, 0]],
        [[0, 0, 0],
         [1, 1, 1],
         [0, 0, 1]],
        [[0, 1, 0],
         [0, 1, 0],
         [1, 1, 0]]
    ],
    'L': [
        [[0, 0, 1],
         [1, 1, 1],
         [0, 0, 0]],
        [[0, 1, 0],
         [0, 1, 0],
         [0, 1, 1]],
        [[0, 0, 0],
         [1, 1, 1],
         [1, 0, 0]],
        [[1, 1, 0],
         [0, 1, 0],
         [0, 1, 0]]
    ]
}


class Tetromino:
    """
    Represents a tetromino piece with position, rotation, and shape data.
    
    Attributes:
        shape_type (str): The type of tetromino ('I', 'O', 'T', 'S', 'Z', 'J', 'L')
        x (int): Horizontal position on the board
        y (int): Vertical position on the board
        rotation (int): Current rotation state (0-3 for most pieces)
        color (tuple): RGB color values for rendering
    """
    
    def __init__(self, shape_type, x, y):
        """
        Initialize a new tetromino piece.
        
        Args:
            shape_type (str): Type of piece to create
            x (int): Starting x position
            y (int): Starting y position
        """
        self.shape_type = shape_type
        self.x = x
        self.y = y
        self.rotation = 0
        self.color = COLORS[shape_type]
        
    def get_shape(self):
        """Get current shape based on rotation state."""
        return TETROMINOES[self.shape_type][self.rotation]
    
    def get_rotated_shape(self, rotation_offset=1):
        """Get shape after applying rotation offset."""
        shapes = TETROMINOES[self.shape_type]
        new_rotation = (self.rotation + rotation_offset) % len(shapes)
        return shapes[new_rotation]
    
    def rotate(self):
        """Rotate the piece clockwise by one step."""
        shapes = TETROMINOES[self.shape_type]
        self.rotation = (self.rotation + 1) % len(shapes)
    
    def get_cells(self, shape=None, x=None, y=None):
        """
        Get list of occupied board cells for this piece.
        
        Args:
            shape: Optional shape array to use instead of current
            x: Optional x position to use instead of current
            y: Optional y position to use instead of current
            
        Returns:
            List of (x, y) tuples representing occupied cells
        """
        if shape is None:
            shape = self.get_shape()
        if x is None:
            x = self.x
        if y is None:
            y = self.y
            
        cells = []
        for row in range(len(shape)):
            for col in range(len(shape[row])):
                if shape[row][col]:
                    cells.append((x + col, y + row))
        return cells


class TetrisGame:
    """
    Main Tetris game class handling game logic, state management, and rendering.
    
    This class manages the complete game including:
    - Game board state and piece management
    - Input handling with DAS (Delayed Auto Shift) and ARR (Auto Repeat Rate)
    - Line clearing and scoring system
    - Pygame rendering and main game loop
    """
    
    def __init__(self):
        """Initialize the Tetris game with default settings."""
        self.screen = pygame.display.set_mode((WINDOW_WIDTH, WINDOW_HEIGHT))
        pygame.display.set_caption("Tetris")
        self.clock = pygame.time.Clock()
        self.font = pygame.font.Font(None, 36)
        self.small_font = pygame.font.Font(None, 24)
        
        # Game state
        self.board = [[None for _ in range(BOARD_WIDTH)] for _ in range(BOARD_HEIGHT)]
        self.current_piece = None
        self.next_piece = None
        self.score = 0
        self.level = 1
        self.lines_cleared = 0
        self.fall_time = 0
        self.fall_speed = 500  # milliseconds
        self.game_over = False
        self.paused = False
        
        # Initialize piece bag for 7-bag randomization
        self.piece_bag = []
        
        # Input handling with DAS/ARR
        self.keys_pressed = set()
        self.key_repeat_time = {}
        self.das_delay = 100  # Delayed Auto Shift delay (ms)
        self.arr_rate = 50    # Auto Repeat Rate (ms)
        
        self.spawn_new_piece()
        
    def get_bag(self):
        """Generate shuffled bag of all 7 tetromino types."""
        pieces = list(TETROMINOES.keys())
        random.shuffle(pieces)
        return pieces
    
    def spawn_new_piece(self):
        """Spawn a new piece at the top of the board using 7-bag system."""
        if not self.piece_bag:
            self.piece_bag = self.get_bag()
            
        if self.next_piece is None:
            shape_type = self.piece_bag.pop(0)
        else:
            shape_type = self.next_piece
            
        if not self.piece_bag:
            self.piece_bag = self.get_bag()
        self.next_piece = self.piece_bag.pop(0)
        
        # Spawn at top center with proper positioning for each piece type
        start_x = BOARD_WIDTH // 2 - 1
        if shape_type == 'I':
            start_y = -1  # I piece needs to start higher
        elif shape_type == 'O':
            start_x = BOARD_WIDTH // 2 - 1
            start_y = 0
        else:
            start_y = 0
        
        self.current_piece = Tetromino(shape_type, start_x, start_y)
        
        # Check for game over
        if not self.is_valid_position(self.current_piece):
            self.game_over = True
    
    def is_valid_position(self, piece, shape=None, x=None, y=None):
        """
        Check if piece position is valid (within bounds and no collisions).
        
        Args:
            piece (Tetromino): The piece to check
            shape: Optional shape to check instead of piece's current shape
            x: Optional x position to check
            y: Optional y position to check
            
        Returns:
            bool: True if position is valid, False otherwise
        """
        cells = piece.get_cells(shape, x, y)
        
        for cell_x, cell_y in cells:
            # Check boundaries
            if cell_x < 0 or cell_x >= BOARD_WIDTH or cell_y >= BOARD_HEIGHT:
                return False
            
            # Check collision with placed pieces (ignore cells above board)
            if cell_y >= 0 and self.board[cell_y][cell_x] is not None:
                return False
                
        return True
    
    def move_piece(self, dx, dy):
        """
        Move current piece by specified offset.
        
        Args:
            dx (int): Horizontal offset
            dy (int): Vertical offset
            
        Returns:
            bool: True if move was successful, False otherwise
        """
        if not self.current_piece or self.game_over or self.paused:
            return False
            
        new_x = self.current_piece.x + dx
        new_y = self.current_piece.y + dy
        
        if self.is_valid_position(self.current_piece, x=new_x, y=new_y):
            self.current_piece.x = new_x
            self.current_piece.y = new_y
            return True
        return False
    
    def rotate_piece(self):
        """
        Rotate current piece clockwise with wall kick system.
        
        Returns:
            bool: True if rotation was successful, False otherwise
        """
        if not self.current_piece or self.game_over or self.paused:
            return False
            
        rotated_shape = self.current_piece.get_rotated_shape()
        
        # Try rotation at current position
        if self.is_valid_position(self.current_piece, shape=rotated_shape):
            self.current_piece.rotate()
            return True
            
        # Try wall kicks (simple version)
        for kick_x in [-1, 1, -2, 2]:
            if self.is_valid_position(self.current_piece, shape=rotated_shape, 
                                    x=self.current_piece.x + kick_x):
                self.current_piece.x += kick_x
                self.current_piece.rotate()
                return True
                
        return False
    
    def hard_drop(self):
        """Drop piece instantly to the bottom and lock it."""
        if not self.current_piece or self.game_over or self.paused:
            return
            
        drop_distance = 0
        while self.move_piece(0, 1):
            drop_distance += 1
            
        self.score += drop_distance * 2
        self.lock_piece()
    
    def lock_piece(self):
        """Lock current piece to the board and handle line clearing."""
        if not self.current_piece:
            return
            
        # Place piece on board
        cells = self.current_piece.get_cells()
        for cell_x, cell_y in cells:
            if cell_y >= 0:  # Only place cells that are on the board
                self.board[cell_y][cell_x] = self.current_piece.color
        
        # Clear lines
        lines_cleared = self.clear_lines()
        
        # Update score based on standard Tetris scoring
        if lines_cleared > 0:
            line_scores = {1: 100, 2: 300, 3: 500, 4: 800}
            self.score += line_scores.get(lines_cleared, 0) * self.level
            self.lines_cleared += lines_cleared
            
            # Level up every 10 lines
            new_level = (self.lines_cleared // 10) + 1
            if new_level > self.level:
                self.level = new_level
                self.fall_speed = max(50, 500 - (self.level - 1) * 50)
        
        # Spawn new piece
        self.spawn_new_piece()
    
    def clear_lines(self):
        """
        Clear completed lines and return count cleared.
        
        Returns:
            int: Number of lines cleared
        """
        lines_to_clear = []
        
        # Find complete lines
        for y in range(BOARD_HEIGHT):
            if all(self.board[y][x] is not None for x in range(BOARD_WIDTH)):
                lines_to_clear.append(y)
        
        # Remove complete lines and add empty lines at top
        for y in sorted(lines_to_clear, reverse=True):
            del self.board[y]
            self.board.insert(0, [None for _ in range(BOARD_WIDTH)])
        
        return len(lines_to_clear)
    
    def update(self, dt):
        """
        Update game state including piece falling.
        
        Args:
            dt (int): Delta time in milliseconds
        """
        if self.game_over or self.paused:
            return
            
        # Handle piece falling
        self.fall_time += dt
        if self.fall_time >= self.fall_speed:
            if not self.move_piece(0, 1):
                self.lock_piece()
            self.fall_time = 0
    
    def handle_input(self, dt):
        if self.game_over or self.paused:
            return
        keys = pygame.key.get_pressed()
        current_time = pygame.time.get_ticks()

        for key, dx in [(pygame.K_LEFT, -1), (pygame.K_RIGHT, 1)]:
            if keys[key]:
                if key not in self.keys_pressed:
                    self.keys_pressed.add(key)
                    self.key_repeat_time[key] = current_time + self.das_delay
                    self.move_piece(dx, 0)
                elif current_time >= self.key_repeat_time.get(key, 0):
                    self.key_repeat_time[key] = current_time + self.arr_rate
                    self.move_piece(dx, 0)
            else:
                self.keys_pressed.discard(key)

        if keys[pygame.K_DOWN]:
            self.move_piece(0, 1)

    def handle_event(self, event):
        if event.type != pygame.KEYDOWN:
            return
        if event.key == pygame.K_p or event.key == pygame.K_ESCAPE:
            if not self.game_over:
                self.paused = not self.paused
            return
        if self.paused:
            return
        if self.game_over:
            if event.key == pygame.K_r:
                self.__init__()
            return
        if event.key == pygame.K_UP:
            self.rotate_piece()
        elif event.key == pygame.K_SPACE:
            self.hard_drop()

    def draw(self):
        self.screen.fill(BLACK)
        self.draw_board()
        self.draw_current_piece()
        self.draw_ghost_piece()
        self.draw_next_piece()
        self.draw_ui()
        if self.paused:
            self.draw_overlay("PAUSED", "Press ESC to resume")
        if self.game_over:
            self.draw_overlay("GAME OVER", f"Score: {self.score} | Press R to restart")
        pygame.display.flip()

    def draw_board(self):
        # Draw border
        border = pygame.Rect(BOARD_X - 2, BOARD_Y - 2,
                            BOARD_WIDTH * CELL_SIZE + 4, BOARD_HEIGHT * CELL_SIZE + 4)
        pygame.draw.rect(self.screen, GRAY, border, 2)

        # Draw grid and placed pieces
        for y in range(BOARD_HEIGHT):
            for x in range(BOARD_WIDTH):
                rect = pygame.Rect(BOARD_X + x * CELL_SIZE, BOARD_Y + y * CELL_SIZE,
                                  CELL_SIZE, CELL_SIZE)
                if self.board[y][x]:
                    pygame.draw.rect(self.screen, self.board[y][x], rect)
                    pygame.draw.rect(self.screen, WHITE, rect, 1)
                else:
                    pygame.draw.rect(self.screen, DARK_GRAY, rect, 1)

    def draw_current_piece(self):
        if not self.current_piece:
            return
        for x, y in self.current_piece.get_cells():
            if y >= 0:
                rect = pygame.Rect(BOARD_X + x * CELL_SIZE, BOARD_Y + y * CELL_SIZE,
                                  CELL_SIZE, CELL_SIZE)
                pygame.draw.rect(self.screen, self.current_piece.color, rect)
                pygame.draw.rect(self.screen, WHITE, rect, 1)

    def draw_ghost_piece(self):
        if not self.current_piece:
            return
        ghost_y = self.current_piece.y
        while self.is_valid_position(self.current_piece, y=ghost_y + 1):
            ghost_y += 1
        if ghost_y == self.current_piece.y:
            return
        for x, y in self.current_piece.get_cells(y=ghost_y):
            if y >= 0:
                rect = pygame.Rect(BOARD_X + x * CELL_SIZE, BOARD_Y + y * CELL_SIZE,
                                  CELL_SIZE, CELL_SIZE)
                pygame.draw.rect(self.screen, self.current_piece.color, rect, 1)

    def draw_next_piece(self):
        label = self.font.render("Next", True, WHITE)
        self.screen.blit(label, (BOARD_X + BOARD_WIDTH * CELL_SIZE + 30, BOARD_Y))
        if self.next_piece:
            shape = TETROMINOES[self.next_piece][0]
            color = COLORS[self.next_piece]
            for r, row in enumerate(shape):
                for c, val in enumerate(row):
                    if val:
                        rect = pygame.Rect(
                            BOARD_X + BOARD_WIDTH * CELL_SIZE + 30 + c * CELL_SIZE,
                            BOARD_Y + 40 + r * CELL_SIZE,
                            CELL_SIZE, CELL_SIZE)
                        pygame.draw.rect(self.screen, color, rect)
                        pygame.draw.rect(self.screen, WHITE, rect, 1)

    def draw_ui(self):
        x = 30
        score_text = self.font.render(f"Score: {self.score}", True, WHITE)
        level_text = self.font.render(f"Level: {self.level}", True, WHITE)
        lines_text = self.font.render(f"Lines: {self.lines_cleared}", True, WHITE)
        self.screen.blit(score_text, (x, BOARD_Y))
        self.screen.blit(level_text, (x, BOARD_Y + 40))
        self.screen.blit(lines_text, (x, BOARD_Y + 80))

        controls = [
            "Controls:",
            "Left/Right: Move",
            "Up: Rotate",
            "Down: Soft drop",
            "Space: Hard drop",
            "ESC: Pause",
            "R: Restart"
        ]
        for i, text in enumerate(controls):
            t = self.small_font.render(text, True, GRAY)
            self.screen.blit(t, (x, BOARD_Y + 150 + i * 22))

    def draw_overlay(self, title, subtitle):
        overlay = pygame.Surface((WINDOW_WIDTH, WINDOW_HEIGHT), pygame.SRCALPHA)
        overlay.fill((0, 0, 0, 160))
        self.screen.blit(overlay, (0, 0))
        title_surf = self.font.render(title, True, WHITE)
        sub_surf = self.small_font.render(subtitle, True, GRAY)
        self.screen.blit(title_surf, title_surf.get_rect(center=(WINDOW_WIDTH // 2, WINDOW_HEIGHT // 2 - 20)))
        self.screen.blit(sub_surf, sub_surf.get_rect(center=(WINDOW_WIDTH // 2, WINDOW_HEIGHT // 2 + 20)))

    def run(self):
        while True:
            dt = self.clock.tick(60)
            for event in pygame.event.get():
                if event.type == pygame.QUIT:
                    pygame.quit()
                    sys.exit()
                self.handle_event(event)
            if not self.paused and not self.game_over:
                self.handle_input(dt)
                self.update(dt)
            self.draw()


if __name__ == "__main__":
    game = TetrisGame()
    game.run()