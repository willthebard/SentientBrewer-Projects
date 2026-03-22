import pygame
import sys
import math
import random
from enum import Enum
from dataclasses import dataclass
from typing import Optional, Tuple

# Initialize Pygame
pygame.init()

# Constants
SCREEN_WIDTH = 1024
SCREEN_HEIGHT = 768
FPS = 60

# Colors (RGB)
BLACK = (0, 0, 0)
WHITE = (255, 255, 255)
GRAY = (128, 128, 128)
LIGHT_GRAY = (200, 200, 200)
DARK_GRAY = (64, 64, 64)

# Game Settings
PADDLE_WIDTH = 15
PADDLE_HEIGHT = 100
PADDLE_SPEED = 400
PADDLE_MARGIN = 50
BALL_RADIUS = 8
BALL_SPEED_INITIAL = 300
BALL_SPEED_INCREMENT = 20
WIN_SCORE = 11
MAX_BALL_SPEED = 800

# AI Settings
AI_DIFFICULTY = {
    'easy': {'accuracy': 0.6, 'reaction_time': 0.3, 'max_error': 80},
    'medium': {'accuracy': 0.8, 'reaction_time': 0.15, 'max_error': 40},
    'hard': {'accuracy': 0.95, 'reaction_time': 0.05, 'max_error': 15}
}

class GameState(Enum):
    MENU = "menu"
    SINGLE_PLAYER = "single_player"
    TWO_PLAYER = "two_player"
    GAME_OVER = "game_over"
    PAUSED = "paused"

@dataclass
class InputState:
    player1_up: bool = False       # Held (for gameplay)
    player1_down: bool = False
    player2_up: bool = False
    player2_down: bool = False
    up_just: bool = False          # Single press (for menus)
    down_just: bool = False
    escape_pressed: bool = False
    enter_pressed: bool = False
    space_pressed: bool = False

class Paddle:
    def __init__(self, x: float, y: float, player_id: int):
        self.x = x
        self.y = y
        self.width = PADDLE_WIDTH
        self.height = PADDLE_HEIGHT
        self.speed = PADDLE_SPEED
        self.velocity_y = 0
        self.player_id = player_id
        self.color = WHITE
    
    def update(self, dt: float, input_state: InputState):
        # Handle input based on player ID
        if self.player_id == 1:
            if input_state.player1_up:
                self.velocity_y = -self.speed
            elif input_state.player1_down:
                self.velocity_y = self.speed
            else:
                self.velocity_y = 0
        elif self.player_id == 2:
            if input_state.player2_up:
                self.velocity_y = -self.speed
            elif input_state.player2_down:
                self.velocity_y = self.speed
            else:
                self.velocity_y = 0
        
        # Update position
        self.y += self.velocity_y * dt
        
        # Keep paddle within screen bounds
        if self.y < 0:
            self.y = 0
        elif self.y + self.height > SCREEN_HEIGHT:
            self.y = SCREEN_HEIGHT - self.height
    
    def ai_update(self, dt: float, target_y: float):
        # AI movement logic
        paddle_center = self.y + self.height / 2
        diff = target_y - paddle_center
        
        if abs(diff) > 5:  # Dead zone to prevent jittering
            if diff < 0:
                self.velocity_y = -self.speed
            else:
                self.velocity_y = self.speed
        else:
            self.velocity_y = 0
        
        # Update position
        self.y += self.velocity_y * dt
        
        # Keep paddle within screen bounds
        if self.y < 0:
            self.y = 0
        elif self.y + self.height > SCREEN_HEIGHT:
            self.y = SCREEN_HEIGHT - self.height
    
    def render(self, screen: pygame.Surface):
        pygame.draw.rect(screen, self.color, (self.x, self.y, self.width, self.height))
    
    def get_rect(self) -> pygame.Rect:
        return pygame.Rect(self.x, self.y, self.width, self.height)
    
    def get_center_y(self) -> float:
        return self.y + self.height / 2

class Ball:
    def __init__(self, x: float, y: float):
        self.x = x
        self.y = y
        self.radius = BALL_RADIUS
        self.velocity_x = 0
        self.velocity_y = 0
        self.speed = BALL_SPEED_INITIAL
        self.color = WHITE
        self.last_hit_by = None
        self.reset_ball()
    
    def reset_ball(self):
        self.x = SCREEN_WIDTH / 2
        self.y = SCREEN_HEIGHT / 2
        
        # Random initial direction
        angle = random.uniform(-math.pi/4, math.pi/4)  # Between -45 and 45 degrees
        direction = random.choice([-1, 1])  # Left or right
        
        self.velocity_x = direction * self.speed * math.cos(angle)
        self.velocity_y = self.speed * math.sin(angle)
        
        self.last_hit_by = None
    
    def update(self, dt: float):
        # Update position
        self.x += self.velocity_x * dt
        self.y += self.velocity_y * dt
        
        # Collision with top and bottom walls with position correction
        if self.y - self.radius <= 0:
            self.velocity_y = abs(self.velocity_y)
            self.y = self.radius
        elif self.y + self.radius >= SCREEN_HEIGHT:
            self.velocity_y = -abs(self.velocity_y)
            self.y = SCREEN_HEIGHT - self.radius
    
    def render(self, screen: pygame.Surface):
        pygame.draw.circle(screen, self.color, (int(self.x), int(self.y)), self.radius)
    
    def get_rect(self) -> pygame.Rect:
        return pygame.Rect(self.x - self.radius, self.y - self.radius, 
                          self.radius * 2, self.radius * 2)
    
    def reverse_x(self):
        self.velocity_x = -self.velocity_x
    
    def increase_speed(self, factor: float = 1.05):
        current_speed = math.sqrt(self.velocity_x ** 2 + self.velocity_y ** 2)
        if current_speed * factor <= MAX_BALL_SPEED:
            self.velocity_x *= factor
            self.velocity_y *= factor

class AIPlayer:
    def __init__(self, difficulty: str = "medium"):
        self.difficulty = difficulty
        self.config = AI_DIFFICULTY[difficulty]
        self.reaction_timer = 0
        self.target_y = SCREEN_HEIGHT / 2
        self.last_ball_x = 0
    
    def update(self, paddle: Paddle, ball, dt: float):
        self.reaction_timer += dt
        
        # Only update target if enough time has passed (reaction time)
        if self.reaction_timer >= self.config['reaction_time']:
            self.reaction_timer = 0
            self.target_y = self.calculate_target(paddle, ball)
        
        return self.target_y
    
    def calculate_target(self, paddle: Paddle, ball) -> float:
        # Predict where the ball will be when it reaches the paddle
        if ball.velocity_x > 0 and abs(ball.velocity_x) > 0.1:  # Ball moving towards AI paddle
            time_to_paddle = (paddle.x - ball.x) / ball.velocity_x
            if time_to_paddle > 0:  # Only predict if ball is actually coming towards paddle
                predicted_y = ball.y + ball.velocity_y * time_to_paddle
                
                # Account for wall bounces (simplified - only one bounce)
                if predicted_y < 0:
                    predicted_y = -predicted_y
                elif predicted_y > SCREEN_HEIGHT:
                    predicted_y = 2 * SCREEN_HEIGHT - predicted_y
                
                target = predicted_y
            else:
                target = SCREEN_HEIGHT / 2
        else:
            # Ball moving away, move to center
            target = SCREEN_HEIGHT / 2
        
        # Add difficulty-based error
        if random.random() > self.config['accuracy']:
            error = random.uniform(-self.config['max_error'], self.config['max_error'])
            target += error
        
        return max(paddle.height / 2, min(SCREEN_HEIGHT - paddle.height / 2, target))

class CollisionDetector:
    @staticmethod
    def ball_paddle_collision(ball: Ball, paddle: Paddle) -> bool:
        # More precise collision detection using distance
        paddle_rect = paddle.get_rect()
        ball_center = (ball.x, ball.y)
        
        # Find closest point on paddle to ball center
        closest_x = max(paddle_rect.left, min(ball_center[0], paddle_rect.right))
        closest_y = max(paddle_rect.top, min(ball_center[1], paddle_rect.bottom))
        
        # Calculate distance
        distance = math.sqrt((ball_center[0] - closest_x) ** 2 + (ball_center[1] - closest_y) ** 2)
        
        return distance < ball.radius
    
    @staticmethod
    def handle_paddle_collision(ball: Ball, paddle: Paddle):
        # Calculate relative position of collision on paddle (0 to 1)
        paddle_center = paddle.get_center_y()
        relative_intersect_y = (ball.y - paddle_center) / (paddle.height / 2)
        
        # Clamp the relative intersection to prevent extreme angles
        relative_intersect_y = max(-1, min(1, relative_intersect_y))
        
        # Calculate bounce angle (max 60 degrees)
        bounce_angle = relative_intersect_y * math.pi / 3
        
        # Calculate new velocity
        speed = math.sqrt(ball.velocity_x ** 2 + ball.velocity_y ** 2)
        
        if paddle.player_id == 1:  # Left paddle
            ball.velocity_x = abs(speed * math.cos(bounce_angle))  # Ensure positive x velocity
            ball.velocity_y = speed * math.sin(bounce_angle)
            # Move ball outside paddle to prevent sticking
            ball.x = paddle.x + paddle.width + ball.radius + 1
        else:  # Right paddle
            ball.velocity_x = -abs(speed * math.cos(bounce_angle))  # Ensure negative x velocity
            ball.velocity_y = speed * math.sin(bounce_angle)
            # Move ball outside paddle to prevent sticking
            ball.x = paddle.x - ball.radius - 1
        
        # Increase ball speed slightly
        ball.increase_speed(1.05)
        ball.last_hit_by = paddle.player_id
    
    @staticmethod
    def check_goal(ball: Ball) -> Optional[int]:
        if ball.x + ball.radius < 0:
            return 2  # Player 2 scores
        elif ball.x - ball.radius > SCREEN_WIDTH:
            return 1  # Player 1 scores
        return None

class InputHandler:
    def __init__(self):
        self.event_keys = set()  # Keys pressed this frame (single-press)
        self.held_keys = set()   # Keys currently held down

    def handle_event(self, event):
        if event.type == pygame.KEYDOWN:
            self.event_keys.add(event.key)
            self.held_keys.add(event.key)
        elif event.type == pygame.KEYUP:
            self.held_keys.discard(event.key)

    def update(self) -> InputState:
        just = self.event_keys.copy()
        self.event_keys.clear()

        return InputState(
            player1_up=pygame.K_w in self.held_keys,
            player1_down=pygame.K_s in self.held_keys,
            player2_up=pygame.K_UP in self.held_keys,
            player2_down=pygame.K_DOWN in self.held_keys,
            up_just=pygame.K_w in just or pygame.K_UP in just,
            down_just=pygame.K_s in just or pygame.K_DOWN in just,
            escape_pressed=pygame.K_ESCAPE in just,
            enter_pressed=pygame.K_RETURN in just,
            space_pressed=pygame.K_SPACE in just
        )

class GameManager:
    def __init__(self):
        self.screen = pygame.display.set_mode((SCREEN_WIDTH, SCREEN_HEIGHT))
        pygame.display.set_caption("Pong Game")
        self.clock = pygame.time.Clock()
        
        # Fonts
        self.font_large = pygame.font.Font(None, 72)
        self.font_medium = pygame.font.Font(None, 48)
        self.font_small = pygame.font.Font(None, 36)
        
        # Game state
        self.state = GameState.MENU
        self.previous_state = GameState.MENU
        self.menu_selection = 0
        self.menu_options = ["Single Player", "Two Player", "Quit"]
        self.pause_selection = 0

        # Game objects
        self.paddle1 = None
        self.paddle2 = None
        self.ball = None
        self.ai_player = None
        
        # Score
        self.player1_score = 0
        self.player2_score = 0
        self.winner = None
        
        # Input handling
        self.input_handler = InputHandler()
        
        # Game mode
        self.is_single_player = False
        
        # Initialize game objects
        self.reset_game()
    
    def reset_game(self):
        """Reset game objects and scores"""
        self.paddle1 = Paddle(PADDLE_MARGIN, SCREEN_HEIGHT // 2 - PADDLE_HEIGHT // 2, 1)
        self.paddle2 = Paddle(SCREEN_WIDTH - PADDLE_MARGIN - PADDLE_WIDTH, 
                             SCREEN_HEIGHT // 2 - PADDLE_HEIGHT // 2, 2)
        self.ball = Ball(SCREEN_WIDTH // 2, SCREEN_HEIGHT // 2)
        self.ai_player = AIPlayer("medium")
        
        self.player1_score = 0
        self.player2_score = 0
        self.winner = None
    
    def handle_events(self):
        for event in pygame.event.get():
            if event.type == pygame.QUIT:
                return False
            
            self.input_handler.handle_event(event)
        
        return True
    
    def update(self, dt: float):
        input_state = self.input_handler.update()
        
        if self.state == GameState.MENU:
            self.update_menu(input_state)
        elif self.state in [GameState.SINGLE_PLAYER, GameState.TWO_PLAYER]:
            self.update_game(input_state, dt)
        elif self.state == GameState.GAME_OVER:
            self.update_game_over(input_state)
        elif self.state == GameState.PAUSED:
            self.update_pause(input_state)
    
    def update_menu(self, input_state: InputState):
        if input_state.up_just:
            self.menu_selection = (self.menu_selection - 1) % 3
        if input_state.down_just:
            self.menu_selection = (self.menu_selection + 1) % 3
        if input_state.enter_pressed or input_state.space_pressed:
            if self.menu_selection == 0:
                self.is_single_player = True
                self.state = GameState.SINGLE_PLAYER
                self.reset_game()
            elif self.menu_selection == 1:
                self.is_single_player = False
                self.state = GameState.TWO_PLAYER
                self.reset_game()
            elif self.menu_selection == 2:
                pygame.quit()
                sys.exit()

    def update_game(self, input_state: InputState, dt: float):
        if input_state.escape_pressed:
            self.state = GameState.PAUSED
            self.pause_selection = 0
            return

        # Player 1 paddle
        self.paddle1.update(dt, input_state)

        # Player 2 / AI
        if self.is_single_player:
            target_y = self.ai_player.update(self.paddle2, self.ball, dt)
            self.paddle2.ai_update(dt, target_y)
        else:
            self.paddle2.update(dt, input_state)

        # Ball
        self.ball.update(dt)

        # Ball-paddle collisions
        if CollisionDetector.ball_paddle_collision(self.ball, self.paddle1):
            CollisionDetector.handle_paddle_collision(self.ball, self.paddle1)
        if CollisionDetector.ball_paddle_collision(self.ball, self.paddle2):
            CollisionDetector.handle_paddle_collision(self.ball, self.paddle2)

        # Scoring
        scorer = CollisionDetector.check_goal(self.ball)
        if scorer is not None:
            if scorer == 1:
                self.player1_score += 1
            else:
                self.player2_score += 1

            if self.player1_score >= WIN_SCORE:
                self.winner = "Player 1"
                self.state = GameState.GAME_OVER
            elif self.player2_score >= WIN_SCORE:
                self.winner = "Player 2" if not self.is_single_player else "AI"
                self.state = GameState.GAME_OVER
            else:
                self.ball.reset_ball()

    def update_game_over(self, input_state: InputState):
        if input_state.enter_pressed or input_state.space_pressed:
            self.state = GameState.MENU
            self.menu_selection = 0

    def update_pause(self, input_state: InputState):
        if input_state.up_just:
            self.pause_selection = (self.pause_selection - 1) % 3
        if input_state.down_just:
            self.pause_selection = (self.pause_selection + 1) % 3
        if input_state.enter_pressed or input_state.space_pressed:
            if self.pause_selection == 0:  # Resume
                if self.is_single_player:
                    self.state = GameState.SINGLE_PLAYER
                else:
                    self.state = GameState.TWO_PLAYER
            elif self.pause_selection == 1:  # Main Menu
                self.state = GameState.MENU
                self.menu_selection = 0
            elif self.pause_selection == 2:  # Quit
                pygame.quit()
                sys.exit()
        if input_state.escape_pressed:
            if self.is_single_player:
                self.state = GameState.SINGLE_PLAYER
            else:
                self.state = GameState.TWO_PLAYER

    def draw(self):
        self.screen.fill(BLACK)

        if self.state == GameState.MENU:
            self.draw_menu()
        elif self.state in [GameState.SINGLE_PLAYER, GameState.TWO_PLAYER]:
            self.draw_game()
        elif self.state == GameState.GAME_OVER:
            self.draw_game()
            self.draw_game_over()
        elif self.state == GameState.PAUSED:
            self.draw_game()
            self.draw_pause()

        pygame.display.flip()

    def draw_menu(self):
        font_large = pygame.font.Font(None, 80)
        font_med = pygame.font.Font(None, 48)

        title = font_large.render("PONG", True, WHITE)
        self.screen.blit(title, title.get_rect(center=(SCREEN_WIDTH // 2, 150)))

        options = ["Single Player (vs AI)", "Two Players", "Quit"]
        for i, opt in enumerate(options):
            color = WHITE if i == self.menu_selection else GRAY
            text = font_med.render(opt, True, color)
            rect = text.get_rect(center=(SCREEN_WIDTH // 2, 320 + i * 70))
            self.screen.blit(text, rect)
            if i == self.menu_selection:
                pygame.draw.rect(self.screen, WHITE, rect.inflate(20, 10), 2)

        controls = pygame.font.Font(None, 28)
        c1 = controls.render("P1: W/S  |  P2: Up/Down  |  ESC: Pause", True, DARK_GRAY)
        self.screen.blit(c1, c1.get_rect(center=(SCREEN_WIDTH // 2, SCREEN_HEIGHT - 50)))

    def draw_game(self):
        # Center line
        for y in range(0, SCREEN_HEIGHT, 20):
            pygame.draw.rect(self.screen, DARK_GRAY, (SCREEN_WIDTH // 2 - 1, y, 2, 10))

        # Paddles and ball use their own render methods
        self.paddle1.render(self.screen)
        self.paddle2.render(self.screen)
        self.ball.render(self.screen)

        # Scores
        font = pygame.font.Font(None, 72)
        s1 = font.render(str(self.player1_score), True, WHITE)
        s2 = font.render(str(self.player2_score), True, WHITE)
        self.screen.blit(s1, (SCREEN_WIDTH // 4 - s1.get_width() // 2, 30))
        self.screen.blit(s2, (3 * SCREEN_WIDTH // 4 - s2.get_width() // 2, 30))

    def draw_game_over(self):
        overlay = pygame.Surface((SCREEN_WIDTH, SCREEN_HEIGHT), pygame.SRCALPHA)
        overlay.fill((0, 0, 0, 128))
        self.screen.blit(overlay, (0, 0))

        font_large = pygame.font.Font(None, 72)
        font_med = pygame.font.Font(None, 36)

        winner_text = font_large.render(f"{self.winner} Wins!", True, WHITE)
        self.screen.blit(winner_text, winner_text.get_rect(center=(SCREEN_WIDTH // 2, SCREEN_HEIGHT // 2 - 40)))

        score_text = font_med.render(f"{self.player1_score} - {self.player2_score}", True, GRAY)
        self.screen.blit(score_text, score_text.get_rect(center=(SCREEN_WIDTH // 2, SCREEN_HEIGHT // 2 + 20)))

        restart = font_med.render("Press SPACE to return to menu", True, GRAY)
        self.screen.blit(restart, restart.get_rect(center=(SCREEN_WIDTH // 2, SCREEN_HEIGHT // 2 + 70)))

    def draw_pause(self):
        overlay = pygame.Surface((SCREEN_WIDTH, SCREEN_HEIGHT), pygame.SRCALPHA)
        overlay.fill((0, 0, 0, 180))
        self.screen.blit(overlay, (0, 0))

        font_large = pygame.font.Font(None, 72)
        font_med = pygame.font.Font(None, 48)

        text = font_large.render("PAUSED", True, WHITE)
        self.screen.blit(text, text.get_rect(center=(SCREEN_WIDTH // 2, SCREEN_HEIGHT // 2 - 100)))

        options = ["Resume", "Main Menu", "Quit"]
        for i, opt in enumerate(options):
            color = WHITE if i == self.pause_selection else GRAY
            t = font_med.render(opt, True, color)
            rect = t.get_rect(center=(SCREEN_WIDTH // 2, SCREEN_HEIGHT // 2 + i * 60))
            self.screen.blit(t, rect)
            if i == self.pause_selection:
                pygame.draw.rect(self.screen, WHITE, rect.inflate(20, 10), 2)

    def run(self):
        clock = pygame.time.Clock()
        while True:
            dt = clock.tick(FPS) / 1000.0
            if not self.handle_events():
                break
            self.update(dt)
            self.draw()
        pygame.quit()
        sys.exit()


if __name__ == "__main__":
    game = GameManager()
    game.run()